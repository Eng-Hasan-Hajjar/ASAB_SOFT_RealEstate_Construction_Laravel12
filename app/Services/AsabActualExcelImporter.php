<?php

namespace App\Services;

use App\Models\{Company, Contact, ImportBatch, Lease, Property, Unit};
use DateTimeImmutable;
use App\Support\{ExcelMoney,RentalSheetRows};
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Imports the actual 14 source workbooks, exported to private JSON.GZ snapshots.
 * Directly populates properties, units, contacts, historical rental/finance screens,
 * while preserving every non-empty source row in the encrypted review archive.
 * Never posts unverified historical amounts to invoices/payments/ledger_entries.
 */
final class AsabActualExcelImporter
{
    private array $counter = [];
    private array $properties = [];
    private array $units = [];
    private array $contacts = [];
    private int $companyId = 0;

    public function execute(Company $company, bool $dryRun = false, bool $withDrafts = true): array
    {
        if ($company->country_code !== 'SA' || $company->currency !== 'SAR') {
            throw new RuntimeException('The 14 Saudi workbooks must be imported into a Saudi / SAR company.');
        }
        $this->companyId = (int) $company->id;
        $this->properties=$this->units=$this->contacts=[];
        $this->counter = array_fill_keys([
            'workbooks', 'worksheets', 'archived_rows', 'properties', 'units', 'contact_links',
            'contacts_created', 'rental_history', 'finance_history', 'draft_leases',
            'cash_receipts', 'bank_receipts', 'cash_expenses', 'bank_auxiliary'
        ], 0);
        $root = storage_path('app/asab-private-import');
        $manifest = $this->readJson($root.'/manifest.json');
        if (($manifest['version'] ?? null) !== 1 || count($manifest['sources'] ?? []) !== 14) {
            throw new RuntimeException('Expected a private manifest with 14 original Excel workbooks (version 1).');
        }
        $this->counter['workbooks'] = 14;
        foreach ($manifest['sources'] as $spec) {
            $this->counter['worksheets'] += (int) $spec['sheet_count'];
            $file = (string) ($spec['file'] ?? '');
            if (! preg_match('/^source_\d{2}\.json\.gz$/', $file)) {
                throw new RuntimeException('Unexpected source name in private manifest.');
            }
            $path = $root.'/'.$file;
            if (! is_file($path) || ! hash_equals((string) $spec['archive_sha256'], hash_file('sha256', $path))) {
                throw new RuntimeException('Missing or modified private source file: '.$file);
            }
            $binary = file_get_contents($path);
            $raw = $binary === false ? false : gzdecode($binary);
            if (! is_string($raw)) throw new RuntimeException('Cannot decompress: '.$file);
            $book = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
            if (! hash_equals((string) $spec['source_sha256'], (string) ($book['source_sha256'] ?? ''))) {
                throw new RuntimeException('Source checksum does not match: '.$file);
            }
            if (count($book['sheets'] ?? []) !== (int) $spec['sheet_count']) {
                throw new RuntimeException('Incorrect sheet count: '.$file);
            }
            $rowCount = array_sum(array_map(static fn (array $s): int => count($s['rows'] ?? []), $book['sheets']));
            if ($rowCount !== (int) $spec['nonempty_rows']) {
                throw new RuntimeException('Incorrect row count: '.$file);
            }
            if ($dryRun) {
                $this->estimateBook($spec, $book, $withDrafts);
                unset($raw, $binary, $book);
                continue;
            }
            // Atomic per workbook, never wipe existing rows and never change passwords.
            DB::transaction(function () use ($company, $spec, $book, $withDrafts): void {
                $this->importBook($company, $spec, $book, $withDrafts);
            }, 2);
            unset($raw, $binary, $book);
        }
        return $this->counter;
    }

    public function receipts(Company $company,bool $apply): array
    {
        if ($company->country_code !== 'SA' || $company->currency !== 'SAR') {
            throw new RuntimeException('Excel receipts require a Saudi / SAR company.');
        }
        $this->companyId=(int)$company->id;
        $this->properties=[];
        $this->counter=array_fill_keys(['archived_rows','finance_history','cash_receipts','bank_receipts','cash_expenses','bank_auxiliary'],0);
        $root=storage_path('app/asab-private-import');
        $manifest=$this->readJson($root.'/manifest.json');
        if (($manifest['version']??null)!==1 || count($manifest['sources']??[])!==14) throw new RuntimeException('Expected the original version-1 private archive.');
        $books=[];
        foreach ($manifest['sources'] as $spec) {
            if (($spec['kind']??null)!=='income') continue;
            $file=(string)($spec['file']??'');
            if (!preg_match('/^source_\d{2}\.json\.gz$/D',$file) || !is_file($path=$root.'/'.$file)
                || !hash_equals((string)$spec['archive_sha256'],hash_file('sha256',$path))) throw new RuntimeException('Invalid private finance snapshot.');
            $raw=gzdecode(file_get_contents($path));
            if ($raw===false) throw new RuntimeException('Could not read the Excel snapshot.');
            $book=json_decode($raw,true,512,JSON_THROW_ON_ERROR);
            if (!hash_equals((string)$spec['source_sha256'],(string)($book['source_sha256']??''))
                || count($book['sheets']??[])!==(int)$spec['sheet_count']
                || array_sum(array_map(fn($sheet)=>count($sheet['rows']??[]),$book['sheets']))!==(int)$spec['nonempty_rows'])
                throw new RuntimeException('Finance source metadata mismatch.');
            $books[]=[$spec,$book];
        }
        if(count($books)!==2)throw new RuntimeException('Expected two income workbooks.');
        foreach($books as [$spec,$book]) {
            if (!$apply) {$this->estimateBook($spec,$book,false);continue;}
            DB::transaction(function()use($company,$spec,$book){
                foreach($book['sheets'] as $sheet)$this->importFinancialSheet($company,(string)$spec['source_label'],$sheet,true);
            });
        }
        return array_intersect_key($this->counter,array_flip(['cash_receipts','bank_receipts']));
    }

    private function estimateBook(array $spec, array $book, bool $withDrafts): void
    {
        $this->counter['archived_rows'] += (int) $spec['nonempty_rows'];
        if ($spec['kind'] === 'property') $this->counter['properties']++;
        foreach ($book['sheets'] as $sheet) {
            if ($spec['kind'] === 'property' && ! empty($sheet['is_unit_sheet'])) {
                $this->counter['units']++;
                $candidates = [];
                foreach ($sheet['candidates'] ?? [] as $candidate) {
                    $name = trim((string) ($candidate['name'] ?? ''));
                    if ($name === '' || mb_strlen($name) > 255) continue;
                    $rowNo = (int) $candidate['source_row'];
                    $candidates[$rowNo] = $candidate;
                    $this->counter['contact_links']++;
                    $contactKey = hash('sha256', $name.'|'.(trim((string) ($candidate['phone'] ?? ''))));
                    if (! isset($this->contacts[$contactKey])) {
                        $this->counter['contacts_created']++;
                        $this->contacts[$contactKey] = true;
                    }
                    if ($withDrafts && ! empty($candidate['valid_draft_lease']) &&
                        ! empty($candidate['starts_on']) && ! empty($candidate['ends_on']) &&
                        $this->money($candidate['source_amount'] ?? null) !== null) {
                        $this->counter['draft_leases']++;
                    }
                }
                foreach(RentalSheetRows::explain($sheet,$candidates) as $entry)$this->counter['rental_history']++;
            }
            if ($spec['kind'] === 'income') {
                $bank = str_contains((string) $sheet['name'], 'بنكي');
                foreach ($sheet['rows'] as $row) {
                    $cells = $row['cells'] ?? [];
                    $seq = trim((string) ($cells['A'] ?? ''));
                    $amount = $this->money($cells[$bank ? 'F' : 'G'] ?? null);
                    if (preg_match('/^\d{1,5}$/', $seq) && $amount !== null && (float) $amount > 0) {
                        $this->counter[$bank ? 'bank_receipts' : 'cash_receipts']++;
                        $this->counter['finance_history']++;
                    }
                    if (! $bank) {
                        $expense = $this->money($cells['L'] ?? null);
                        if ($expense !== null && (float) $expense > 0 && $this->monthDay((string) ($cells['K'] ?? '')) !== null) {
                            $this->counter['cash_expenses']++;
                            $this->counter['finance_history']++;
                        }
                    } else {
                        $aux = $this->money($cells['M'] ?? null);
                        $auxLabel = trim((string) ($cells['L'] ?? ''));
                        if ($aux !== null && (float) $aux > 0 && $auxLabel !== '' &&
                            ! preg_match('/^(الدخل|السحوبات|الصافي|الاجمالي|الإجمالي|المبلغ|البنك|الرصيد)/u', $auxLabel)) {
                            $this->counter['bank_auxiliary']++;
                            $this->counter['finance_history']++;
                        }
                    }
                }
            }
        }
    }

    private function readJson(string $path): array
    {
        if (! is_file($path)) throw new RuntimeException('Private data missing at '.$path);
        $v = json_decode((string) file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
        if (! is_array($v)) throw new RuntimeException('Invalid private data.');
        return $v;
    }

    private function importBook(Company $company, array $spec, array $book, bool $withDrafts): void
    {
        $name = (string) $spec['source_label'];
        $property = null;
        if ($spec['kind'] === 'property') {
            $property = Property::firstOrCreate(['company_id' => $company->id, 'name' => $name], [
                'category' => 'mixed', 'country_code' => 'SA', 'city' => $company->city ?: 'غير محدد',
                'status' => 'active', 'is_published' => false,
                'description' => 'مبنى من واقع دفاتر Excel؛ الإشغال والإيجارات الحالية تحتاج مراجعة.',
            ]);
            if ($property->wasRecentlyCreated) $this->counter['properties']++;
            $this->properties[$this->normalize($name)] = $property;
        }

        // Keep every nonempty row as an encrypted source trail, even if not recognized as a lease/receipt.
        foreach ($book['sheets'] as $sheet) {
            $sheetName = (string) $sheet['name'];
            $profile = $spec['kind'] === 'income'
                ? (str_contains($sheetName, 'بنكي') ? 'bank_income' : 'cash_income') : 'unit_history';
            $batch = ImportBatch::firstOrCreate([
                'company_id' => $company->id, 'source_filename' => $name.'.xlsx',
                'unit_label' => 'sha256:'.$spec['source_sha256'].':'.$profile, 'profile' => $profile,
            ], [
                'created_by' => null, 'property_label' => $property?->name,
                'status' => 'preview', 'rows_total' => 0, 'rows_flagged' => 0,
            ]);
            $archive = [];
            foreach ($sheet['rows'] as $row) {
                $cells = $row['cells'] ?? [];
                $errors = $this->hasSpreadsheetError($cells);
                $payload = ['cells' => $cells, 'formulas' => $row['formulas'] ?? [],
                    'types' => $row['types'] ?? [], 'styles' => $row['styles'] ?? [], 'source' => $name];
                $archive[] = [
                    'import_batch_id' => $batch->id, 'source_sheet' => $sheetName,
                    'row_number' => (int) $row['row'],
                    'payload' => Crypt::encryptString(json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR)),
                    'state' => $errors ? 'flagged' : 'pending_review',
                    'review_note' => $errors ? 'خطأ في صيغة Excel؛ يلزم التدقيق.' : null,
                    'created_at' => now(), 'updated_at' => now(),
                ];
                if (count($archive) >= 150) $this->insertBatch('import_rows', $archive, 'archived_rows');
            }
            $this->insertBatch('import_rows', $archive, 'archived_rows');
            // Count rows for each profile over all of its sheets (same workbook/batch).
            $batch->update([
                'rows_total' => DB::table('import_rows')->where('import_batch_id', $batch->id)->count(),
                'rows_flagged' => DB::table('import_rows')->where('import_batch_id', $batch->id)->where('state', 'flagged')->count(),
            ]);

            if ($property && ! empty($sheet['is_unit_sheet'])) {
                $this->importUnit($company, $property, $name, $sheet, $withDrafts);
            } elseif ($spec['kind'] === 'income') {
                $this->importFinancialSheet($company, $name, $sheet);
            }
        }
    }

    private function importUnit(Company $company, Property $property, string $source, array $sheet, bool $withDrafts): void
    {
        $sheetName = (string) $sheet['name'];
        $unit = Unit::firstOrCreate(['property_id' => $property->id, 'code' => $sheetName], [
            'company_id' => $company->id, 'type' => $sheet['unit_type'] ?: 'other',
            'monthly_rent' => 0, 'status' => 'inactive', 'is_listed' => false,
            'description' => 'وحدة من سجل العميل الأصلي. السعر الحالي وحالة الإشغال غير مؤكَّدين.',
        ]);
        if ((int) $unit->company_id !== $this->companyId) throw new RuntimeException('Unit company mismatch.');
        if ($unit->wasRecentlyCreated) $this->counter['units']++;
        $this->units[$property->id][$this->normalizeUnit($sheetName)][] = $unit;

        $candidates = [];
        foreach ($sheet['candidates'] ?? [] as $candidate) {
            $name = trim((string) ($candidate['name'] ?? ''));
            if ($name === '' || mb_strlen($name) > 255) continue;
            $phone = trim((string) ($candidate['phone'] ?? '')) ?: null;
            $contactKey = hash('sha256', $company->id.'|'.$name.'|'.($phone ?? ''));
            if (! isset($this->contacts[$contactKey])) {
                $contact = Contact::firstOrCreate([
                    'company_id' => $company->id, 'name' => $name, 'phone' => $phone,
                ], [
                    'type' => 'tenant', 'nationality' => $candidate['nationality'] ?: null,
                    'notes' => 'جهة واردة في ملف عقاري تاريخي؛ التأكد من بياناتها قبل اعتمادها كمستأجر حالي.',
                ]);
                if ($contact->wasRecentlyCreated) $this->counter['contacts_created']++;
                $this->contacts[$contactKey] = (int) $contact->id;
            }
            $candidates[(int) $candidate['source_row']] = ['contact_id' => $this->contacts[$contactKey], 'candidate' => $candidate];
            $this->counter['contact_links']++;
            if ($withDrafts && ! empty($candidate['valid_draft_lease']) &&
                ! empty($candidate['starts_on']) && ! empty($candidate['ends_on']) &&
                $this->money($candidate['source_amount'] ?? null) !== null) {
                $sourceKey = $source.'|'.$sheetName.'|'.$candidate['source_row'];
                $contractNumber = 'XL-'.strtoupper(substr(hash('sha256', $sourceKey), 0, 20));
                $lease = Lease::firstOrCreate(['company_id' => $company->id, 'contract_number' => $contractNumber], [
                    'unit_id' => $unit->id, 'contact_id' => $this->contacts[$contactKey],
                    'starts_on' => $candidate['starts_on'], 'ends_on' => $candidate['ends_on'],
                    'rent_amount' => $this->money($candidate['source_amount']),
                    'deposit' => $this->money($candidate['source_deposit'] ?? null) ?? 0,
                    'billing_cycle' => 'custom', 'status' => 'draft',
                    'legacy_receipt_number' => mb_substr((string) ($candidate['receipt_ref'] ?? ''), 0, 120),
                    'notes' => 'مسودة تاريخية من Excel؛ لا تُصدر عنها فواتير أو إشغال تلقائي. المصدر: '.$sourceKey,
                ]);
                if ($lease->wasRecentlyCreated) $this->counter['draft_leases']++;
            }
        }
        $counts=$this->interpretSheet($company,$property,$unit,$source,$sheet,$candidates,true);
        $this->counter['rental_history']+=$counts['created'];
    }

    /** Rebuild inference for an already imported company, without touching other operational data. */
    public function repairRentals(Company $company,bool $apply): array
    {
        if($company->country_code!=='SA'||$company->currency!=='SAR')throw new RuntimeException('اختر مساحة الشركة السعودية التي استوردت السجلات الأصلية.');
        $this->companyId=(int)$company->id;
        $root=storage_path('app/asab-private-import');
        $manifest=$this->readJson($root.'/manifest.json');
        if(($manifest['version']??null)!==1||count($manifest['sources']??[])!==14)throw new RuntimeException('الأرشيف الأصلي غير مكتمل.');
        $jobs=[];
        foreach($manifest['sources'] as $spec){
            if(($spec['kind']??null)!=='property')continue;
            $file=(string)($spec['file']??'');$path=$root.'/'.$file;
            if(!preg_match('/^source_\d{2}\.json\.gz$/D',$file)||!is_file($path)||!hash_equals((string)$spec['archive_sha256'],hash_file('sha256',$path)))
                throw new RuntimeException('أحد ملفات سجل الإيجار مفقود أو لا يطابق البصمة.');
            $book=json_decode((string)gzdecode((string)file_get_contents($path)),true,512,JSON_THROW_ON_ERROR);
            if(($book['source_sha256']??null)!==$spec['source_sha256']||count($book['sheets']??[])!==(int)$spec['sheet_count'])
                throw new RuntimeException('بيانات ورقة الإيجار لا تطابق الفهرس.');
            $source=(string)$spec['source_label'];
            $property=Property::where('company_id',$company->id)->where('name',$source)->sole();
            $units=Unit::where('company_id',$company->id)->where('property_id',$property->id)->get()->keyBy('code');
            foreach($book['sheets'] as $sheet){
                if(empty($sheet['is_unit_sheet']))continue;
                $unit=$units[$sheet['name']]??null;
                if(!$unit)throw new RuntimeException('وحدة من السجل الأصلي لم تعد تطابق رمزها، راجع أسماء الأصول قبل إعادة الربط.');
                $jobs[]=[$property,$unit,$source,$sheet];
            }
        }
        if(count($jobs)!==391)throw new RuntimeException('عدد أوراق الوحدات لا يطابق أرشيف العميل.');
        $totals=[];
        foreach($jobs as [$property,$unit,$source,$sheet]){
            $counts=DB::transaction(function()use($company,$property,$unit,$source,$sheet,$apply){
                $people=[];
                $existing=DB::table('asab_rental_history')->where('company_id',$company->id)->where('unit_id',$unit->id)
                    ->where('source_file',$source)->where('source_sheet',$sheet['name'])->get()->keyBy('source_row');
                foreach($sheet['candidates']??[] as $candidate){
                    $n=(int)$candidate['source_row'];
                    $name=trim((string)($candidate['name']??''));
                    if($name==='')continue;
                    $point=$existing->get($n);
                    $cid=$point?->contact_id;
                    if(!$cid && !$point){
                        $phone=trim((string)($candidate['phone']??''))?:null;
                        $found=Contact::where('company_id',$company->id)->where('name',$name)->where('phone',$phone)->limit(2)->get();
                        $cid=$found->count()===1?$found->first()->id:null;
                    }
                    $people[$n]=['contact_id'=>$cid,'candidate'=>$candidate];
                }
                return $this->interpretSheet($company,$property,$unit,$source,$sheet,$people,$apply,$existing);
            });
            foreach($counts as $kind=>$number)$totals[$kind]=($totals[$kind]??0)+$number;
        }
        return $totals;
    }

    private function interpretSheet(Company $company,Property $property,Unit $unit,string $source,array $sheet,array $people,bool $apply,$known=null):array
    {
        $sheetName=(string)$sheet['name'];
        $known??=DB::table('asab_rental_history')->where('company_id',$company->id)->where('unit_id',$unit->id)
            ->where('source_file',$source)->where('source_sheet',$sheetName)->get()->keyBy('source_row');
        $counter=array_fill_keys(['named','payment_candidate','recorded_amount','period','archive_only','inherited','created','updated','preserved'],0);
        $buffer=[];
        foreach(RentalSheetRows::explain($sheet,$people) as $n=>$entry){
            $row=$entry['row'];$cells=$row['cells']??[];$kind=$entry['kind'];$anchor=$entry['anchor'];
            $person=$anchor?$people[$anchor]:null;
            $original=$known->get($n);
            $counter[$kind]++;
            if($anchor!==null && $anchor!==$n)$counter['inherited']++;
            $fields=['entry_kind'=>$kind,'tenant_origin_row'=>$anchor];
            if($kind!=='archive_only'){
                $fields['contact_id']=$person['contact_id']??null;
                $fields['tenant_label']=$person?mb_substr((string)$person['candidate']['name'],0,255):null;
                $fields['record_type']=$kind==='named'?'tenant':'period';
            }
            if($original){
                // Keep reviewed records and any personal link that was already persisted.
                if($original->review_status!=='needs_review'){$counter['preserved']++;continue;}
                if($original->contact_id && $original->tenant_origin_row===null){
                    unset($fields['contact_id'],$fields['tenant_label']);
                }
                $changed=false;
                foreach($fields as $column=>$value)if((string)($original->{$column}??'')!==(string)($value??'')){$changed=true;break;}
                if($changed){
                    $counter['updated']++;
                    if($apply)DB::table('asab_rental_history')->where('company_id',$company->id)->where('id',$original->id)
                        ->where('review_status','needs_review')->update($fields+['updated_at'=>now()]);
                }
                continue;
            }
            $counter['created']++;
            if(!$apply)continue;
            $rawFrom=trim((string)($cells['F']??''));$rawTo=trim((string)($cells['G']??''));
            $buffer[]=$fields+[
                'company_id'=>$company->id,'property_id'=>$property->id,'unit_id'=>$unit->id,
                'source_key'=>$this->rowHash($source,$sheetName,$n,'rental'),
                'source_file'=>$source,'source_sheet'=>mb_substr($sheetName,0,140),'source_row'=>$n,
                'sequence_label'=>mb_substr(trim((string)($cells['A']??'')),0,40)?:null,
                'reference'=>mb_substr(trim((string)($cells['E']??'')),0,120)?:null,
                'date_from_source'=>mb_substr($rawFrom,0,100)?:null,'date_to_source'=>mb_substr($rawTo,0,100)?:null,
                'starts_on'=>$this->explicitDate($rawFrom),'ends_on'=>$this->explicitDate($rawTo),
                'source_amount'=>$this->money($cells['H']??null),'source_deposit'=>$this->money($cells['I']??null),
                'review_status'=>'needs_review','memo'=>mb_substr(trim((string)($cells['L']??$cells['K']??'')),0,2000)?:null,
                'encrypted_source'=>Crypt::encryptString(json_encode($cells,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR)),
                'created_at'=>now(),'updated_at'=>now(),
            ];
        }
        foreach(array_chunk($buffer,50) as $chunk){
            // Ensure consistent key order across archive-only and usable rows.
            $chunk=array_map(fn($r)=>$r+['contact_id'=>null,'tenant_label'=>null,'record_type'=>'period'], $chunk);
            DB::table('asab_rental_history')->insertOrIgnore($chunk);
        }
        return $counter;
    }

    private function importFinancialSheet(Company $company, string $source, array $sheet, bool $receiptsOnly=false): void
    {
        $bank = str_contains((string) $sheet['name'], 'بنكي');
        $channel = $bank ? 'bank' : 'cash';
        $buffer = [];
        foreach ($sheet['rows'] as $row) {
            $c = $row['cells'] ?? [];
            $sequence = trim((string) ($c['A'] ?? ''));
            $sourceRow = (int) $row['row'];
            $amountCol = $bank ? 'F' : 'G';
            $amount = $this->money($c[$amountCol] ?? null);
            if (preg_match('/^\d{1,5}$/', $sequence) && $amount !== null && (float) $amount > 0) {
                $propertyLabel = trim((string) ($c[$bank ? 'C' : 'D'] ?? ''));
                $unitLabel = trim((string) ($c[$bank ? 'D' : 'E'] ?? ''));
                [$propertyId, $unitId] = $this->findLocation($company, $propertyLabel, $unitLabel);
                $date = trim((string) ($c[$bank ? 'G' : 'B'] ?? ''));
                $buffer[] = $this->financeRow($company, $source, (string) $sheet['name'], $sourceRow,
                    $amountCol, $channel, 'in', $date, $amount, $propertyId, $unitId,
                    $propertyLabel, $unitLabel, $bank ? (string) ($c['B'] ?? '') : null,
                    $bank ? (string) ($c['E'] ?? '') : null,
                    $bank ? null : (string) ($c['C'] ?? ''),
                    (string) ($c['I'] ?? ''), $c);
                if($propertyId || $unitId) {
                    $sourceKey=$this->rowHash($source,(string)$sheet['name'],$sourceRow,'financial:'.$amountCol);
                    $existing=DB::table('asab_financial_history')->where('company_id',$company->id)->where('source_key',$sourceKey)->where('review_status','unverified');
                    if($propertyId)(clone $existing)->whereNull('property_id')->update(['property_id'=>$propertyId,'updated_at'=>now()]);
                    if($unitId)(clone $existing)->where('property_id',$propertyId)->whereNull('unit_id')->update(['unit_id'=>$unitId,'updated_at'=>now()]);
                }
                $this->counter[$bank ? 'bank_receipts' : 'cash_receipts']++;
            }
            // Separate right-hand cash-withdrawals table in columns K/L/M.
            // It is not an invoice payment and is NOT posted to the live finance ledger.
            if ($receiptsOnly) {
                if (count($buffer)>=150) $this->insertBatch('asab_financial_history',$buffer,'finance_history');
                continue;
            }
            if (! $bank) {
                $expenseDate = trim((string) ($c['K'] ?? ''));
                $expense = $this->money($c['L'] ?? null);
                if ($expense !== null && (float) $expense > 0 && $this->monthDay($expenseDate) !== null) {
                    $buffer[] = $this->financeRow($company, $source, (string) $sheet['name'], $sourceRow,
                        'L', 'cash', 'out', $expenseDate, $expense, null, null, null, null, null, null, null,
                        (string) ($c['M'] ?? ''), $c);
                    $this->counter['cash_expenses']++;
                }
            } else {
                // The bank sheet also contains a secondary, mixed-purpose reconciliation table.
                // Keep ONLY clearly labelled auxiliary rows as non-postable review entries.
                $aux = $this->money($c['M'] ?? null);
                $auxLabel = trim((string) ($c['L'] ?? ''));
                if ($aux !== null && (float) $aux > 0 && $auxLabel !== '' &&
                    ! preg_match('/^(الدخل|السحوبات|الصافي|الاجمالي|الإجمالي|المبلغ|البنك|الرصيد)/u', $auxLabel)) {
                    $buffer[] = $this->financeRow($company, $source, (string) $sheet['name'], $sourceRow,
                        'M', 'bank_auxiliary', 'review', '', $aux, null, null, null, null,
                        null, $auxLabel, null, (string) ($c['N'] ?? ''), $c);
                    $this->counter['bank_auxiliary']++;
                }
            }
            if (count($buffer) >= 150) $this->insertBatch('asab_financial_history', $buffer, 'finance_history');
        }
        $this->insertBatch('asab_financial_history', $buffer, 'finance_history');
    }

    private function financeRow(Company $company, string $source, string $sheet, int $row, string $col,
        string $channel, string $flow, string $date, string $amount, ?int $propertyId, ?int $unitId,
        ?string $propertyLabel, ?string $unitLabel, ?string $party, ?string $bankName,
        ?string $voucher, string $memo, array $cells): array
    {
        return [
            'company_id' => $company->id, 'property_id' => $propertyId, 'unit_id' => $unitId,
            'source_key' => $this->rowHash($source, $sheet, $row, 'financial:'.$col),
            'source_file' => $source, 'source_sheet' => mb_substr($sheet, 0, 140),
            'source_row' => $row, 'source_column' => $col,
            'source_date_label' => mb_substr($date, 0, 100) ?: null,
            'event_on' => $this->monthDay($date), 'channel' => $channel, 'flow' => $flow,
            'party_name' => $party ? mb_substr($party, 0, 255) : null,
            'property_label' => $propertyLabel ? mb_substr($propertyLabel, 0, 160) : null,
            'unit_label' => $unitLabel ? mb_substr($unitLabel, 0, 90) : null,
            'voucher_number' => $voucher ? mb_substr($voucher, 0, 120) : null,
            'source_amount' => $amount, 'bank_name' => $bankName ? mb_substr($bankName, 0, 100) : null,
            'review_status' => 'unverified', 'memo' => mb_substr($memo, 0, 2000) ?: null,
            'encrypted_source' => Crypt::encryptString(json_encode($cells, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR)),
            'created_at' => now(), 'updated_at' => now(),
        ];
    }

    private function findLocation(Company $company, string $label, string $unitLabel): array
    {
        if ($label === '') return [null, null];
        $key = $this->normalize($label);
        $property = $this->properties[$key] ?? null;
        if (! $property) {
            // Lookup already imported or manually entered property of the same company.
            foreach (Property::where('company_id', $company->id)->get(['id', 'name']) as $p) {
                $this->properties[$this->normalize((string) $p->name)] = $p;
            }
            $property = $this->properties[$key] ?? null;
        }
        if (! $property) return [null, null];
        if ($unitLabel === '') return [(int) $property->id, null];
        $needle = $this->normalizeUnit($unitLabel);
        $matching = Unit::where('company_id', $company->id)->where('property_id', $property->id)
            ->get(['id','code'])->filter(fn ($u) => $this->normalizeUnit((string) $u->code) === $needle);
        return [(int) $property->id, $matching->count() === 1 ? (int) $matching->first()->id : null];
    }

    private function normalize(string $text): string
    {
        $text = preg_replace('/^(عمارة|شقق)\s*/u', '', trim($text));
        $text = strtr($text, ['أ'=>'ا','إ'=>'ا','آ'=>'ا','ى'=>'ي','ة'=>'ه']);
        return preg_replace('/[^\p{L}\p{N}]/u', '', $text) ?? '';
    }

    private function normalizeUnit(string $text): string
    {
        $text = preg_replace('/^(شقة|شقه|محل|غرفة|غرفه)\s*/u', '', trim($text));
        return $this->normalize($text);
    }

    private function monthDay(string $raw): ?string
    {
        $raw = trim($raw);
        if (! preg_match('/^(\d{1,2})[-\/]([0-9]{1,2})$/', $raw, $matches)) return null;
        $m = (int) $matches[1]; $d = (int) $matches[2];
        // In original financial sheets the order is month/day, including 9-26, 10-27, 11-4.
        return checkdate($m, $d, 2026) ? sprintf('2026-%02d-%02d', $m, $d) : null;
    }

    private function explicitDate(string $raw): ?string
    {
        $raw = trim($raw);
        // Numeric Excel date serials are unambiguous and may safely be represented as Gregorian dates.
        if (preg_match('/^\d{5}$/', $raw) && (int) $raw >= 30000 && (int) $raw <= 80000) {
            return (new DateTimeImmutable('1899-12-30'))->modify('+'.(int) $raw.' days')->format('Y-m-d');
        }
        if (preg_match('/^(20\d\d)-(\d\d)-(\d\d)$/', $raw, $m) && checkdate((int) $m[2], (int) $m[3], (int) $m[1])) return $raw;
        // Never guess Hijri -> Gregorian conversion; keep original date in date_from_source.
        return null;
    }

    private function money(mixed $raw): ?string
    {
        return ExcelMoney::parse($raw);
    }

    private function rowHash(string $source, string $sheet, int $row, string $scope): string
    {
        return hash('sha256', $this->companyId.'|'.$source.'|'.$sheet.'|'.$row.'|'.$scope);
    }

    private function insertBatch(string $table, array &$rows, string $counter): void
    {
        if (! $rows) return;
        $this->counter[$counter] += DB::table($table)->insertOrIgnore($rows);
        $rows = [];
    }

    private function hasSpreadsheetError(array $cells): bool
    {
        foreach ($cells as $value) {
            if (is_string($value) && in_array(strtoupper(trim($value)), ['#VALUE!', '#REF!', '#DIV/0!', '#N/A', '#NAME?', '#NUM!'], true)) return true;
        }
        return false;
    }
}
