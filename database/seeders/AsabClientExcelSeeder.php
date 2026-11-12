<?php

namespace Database\Seeders;

use App\Models\{Company, Contact, ImportBatch, Lease, Property, Unit};
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Imports every nonempty Excel row into the encrypted review staging area.
 * Creates 12 source-derived properties and their identified unit sheets, marked inactive.
 * Optional contacts / historical DRAFT leases are opt-in. No invoices, payments,
 * ledger entries or balances are invented from ambiguous Excel statements.
 * Idempotent by source file + hash, property name, unit code and source row hash.
 */
final class AsabClientExcelSeeder extends Seeder
{
    private int $insertedRows = 0;
    private int $insertedProperties = 0;
    private int $insertedUnits = 0;
    private int $insertedContacts = 0;
    private int $insertedDrafts = 0;

    public function run(): void
    {
        $dir = storage_path('app/asab-private-import');
        $manifestPath = $dir.'/manifest.json';
        if (! is_file($manifestPath)) {
            throw new RuntimeException('Private Excel data not found. Extract ASAB_SOFT_Excel_Private_Seed_Data.zip into the project root first.');
        }
        foreach (['import_batches', 'import_rows', 'financial_accounts'] as $table) {
            if (! \Illuminate\Support\Facades\Schema::hasTable($table)) {
                throw new RuntimeException('Missing '.$table.' table. Run php artisan migrate first.');
            }
        }
        $manifest = json_decode((string) file_get_contents($manifestPath), true, 512, JSON_THROW_ON_ERROR);
        if (($manifest['version'] ?? null) !== 1 || count($manifest['sources'] ?? []) !== 14) {
            throw new RuntimeException('Expected version 1 manifest containing 14 source workbooks.');
        }
        $slug = trim((string) env('ASAB_CLIENT_COMPANY_SLUG', 'asab-pilot-client'));
        $company = Company::where('slug', $slug)->first();
        if (! $company) throw new RuntimeException('Company not found. Run AsabInitializeAccountsSeeder first.');
        if ($company->country_code !== 'SA' || $company->currency !== 'SAR') throw new RuntimeException('Expected Saudi company with SAR currency.');
        $importContacts = filter_var(env('ASAB_IMPORT_CONTACTS', false), FILTER_VALIDATE_BOOLEAN);
        $importDrafts = filter_var(env('ASAB_IMPORT_DRAFT_LEASES', false), FILTER_VALIDATE_BOOLEAN);
        if ($importDrafts && ! $importContacts) throw new RuntimeException('Enable ASAB_IMPORT_CONTACTS=true before importing draft leases.');

        foreach ($manifest['sources'] as $spec) {
            $file = basename((string) ($spec['file'] ?? ''));
            if (! preg_match('/^source_\d{2}\.json\.gz$/', $file)) throw new RuntimeException('Unexpected source filename in manifest.');
            $path = $dir.'/'.$file;
            if (! is_file($path)) throw new RuntimeException('Missing private file '.$file);
            if (! hash_equals((string) $spec['archive_sha256'], hash_file('sha256', $path))) {
                throw new RuntimeException('Integrity check failed: '.$file);
            }
            $raw = gzdecode((string) file_get_contents($path));
            if ($raw === false) throw new RuntimeException('Invalid compressed source: '.$file);
            $book = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
            if (! hash_equals((string) $spec['source_sha256'], (string) ($book['source_sha256'] ?? ''))) {
                throw new RuntimeException('Source checksum mismatch: '.$file);
            }
            if (count($book['sheets'] ?? []) !== (int) $spec['sheet_count']) {
                throw new RuntimeException('Source sheet count mismatch: '.$file);
            }
            $actualRows = array_sum(array_map(static fn (array $sheet): int => count($sheet['rows'] ?? []), $book['sheets']));
            if ($actualRows !== (int) $spec['nonempty_rows']) {
                throw new RuntimeException('Source row count mismatch: '.$file);
            }
            $this->importWorkbook($company, $spec, $book, $importContacts, $importDrafts);
            unset($book, $raw);
        }
        $this->command?->info(sprintf(
            'Excel import complete: %d new staged rows, %d properties, %d units, %d contacts, %d historical DRAFT leases. No financial transactions posted.',
            $this->insertedRows, $this->insertedProperties, $this->insertedUnits, $this->insertedContacts, $this->insertedDrafts
        ));
    }

    private function importWorkbook(Company $company, array $spec, array $book, bool $importContacts, bool $importDrafts): void
    {
        $property = null;
        if ($spec['kind'] === 'property') {
            $name = (string) $spec['source_label'];
            // A source workbook is a property inventory, not a verified public listing.
            $property = Property::firstOrCreate(['company_id' => $company->id, 'name' => $name], [
                'category' => 'mixed', 'country_code' => 'SA', 'city' => $company->city ?: 'غير محدد',
                'status' => 'inactive', 'is_published' => false,
                'description' => 'مستورَد من Excel؛ البيانات التاريخية تحتاج مراجعة قبل الاعتماد أو النشر.',
            ]);
            if ($property->wasRecentlyCreated) $this->insertedProperties++;
        }

        // Separate cash / bank batches for the two finance workbooks.
        $groups = [];
        foreach ($book['sheets'] as $sheet) {
            $group = $spec['kind'] === 'income' ? (str_contains($sheet['name'], 'بنكي') ? 'bank_income' : 'cash_income') : 'unit_history';
            $groups[$group][] = $sheet;
        }
        foreach ($groups as $profile => $sheets) {
            $digest = (string) $spec['source_sha256'];
            $batch = ImportBatch::firstOrCreate([
                'company_id' => $company->id,
                'source_filename' => $spec['source_label'].'.xlsx',
                'unit_label' => 'sha256:'.$digest.':'.$profile,
                'profile' => $profile,
            ], [
                'created_by' => null, 'property_label' => $property?->name,
                'status' => 'preview', 'rows_total' => 0, 'rows_flagged' => 0,
            ]);
            $pending = []; $flagged = 0; $seen = 0;
            foreach ($sheets as $sheet) {
                foreach ($sheet['rows'] as $row) {
                    $seen++;
                    $hasError = $this->hasSpreadsheetError($row['cells'] ?? []);
                    if ($hasError) $flagged++;
                    $payload = [
                        'cells' => $row['cells'] ?? [],
                        'formulas' => $row['formulas'] ?? [],
                        'types' => $row['types'] ?? [],
                        'styles' => $row['styles'] ?? [],
                        'source' => $spec['source_label'],
                    ];
                    $pending[] = [
                        'import_batch_id' => $batch->id,
                        'row_number' => (int) $row['row'],
                        'source_sheet' => $sheet['name'],
                        'payload' => Crypt::encryptString(json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR)),
                        'state' => $hasError ? 'flagged' : 'pending_review',
                        'review_note' => $hasError ? 'يحتوي صف Excel على خطأ محسوب؛ يلزم التصحيح قبل الاعتماد.' : null,
                        'created_at' => now(), 'updated_at' => now(),
                    ];
                    if (count($pending) >= 100) $this->flushRows($pending);
                }
            }
            $this->flushRows($pending);
            $batch->update(['rows_total' => $seen, 'rows_flagged' => $flagged]);
        }
        if ($property !== null) {
            foreach ($book['sheets'] as $sheet) {
                if (empty($sheet['is_unit_sheet'])) continue;
                $code = (string) $sheet['name'];
                $unit = Unit::firstOrCreate(['property_id' => $property->id, 'code' => $code], [
                    'company_id' => $company->id, 'type' => $sheet['unit_type'] ?: 'other',
                    'monthly_rent' => 0, 'status' => 'inactive', 'is_listed' => false,
                    'description' => 'اسم الوحدة محفوظ كما هو في ورقة Excel. حالة الإشغال والقيمة الحالية تحتاجان للتحقق.',
                ]);
                if ($unit->company_id != $company->id) throw new RuntimeException('Unit belongs to another company.');
                if ($unit->wasRecentlyCreated) $this->insertedUnits++;
                if ($importContacts) $this->importCandidates($company, $property, $unit, $spec, $sheet, $importDrafts);
            }
        }
    }

    private function importCandidates(Company $company, Property $property, Unit $unit, array $spec, array $sheet, bool $importDrafts): void
    {
        foreach ($sheet['candidates'] ?? [] as $candidate) {
            $name = trim((string) ($candidate['name'] ?? ''));
            if ($name === '' || mb_strlen($name) > 255) continue;
            $phone = trim((string) ($candidate['phone'] ?? '')) ?: null;
            // Exact source name + source phone; do not merge people by fuzzy matching.
            $contact = Contact::firstOrCreate([
                'company_id' => $company->id, 'name' => $name, 'phone' => $phone,
            ], [
                'type' => 'tenant', 'nationality' => $candidate['nationality'] ?: null,
                'notes' => 'سجل مُستخرج من Excel ويحتاج مراجعة هويّة المستأجر قبل اعتماده.',
            ]);
            if ($contact->wasRecentlyCreated) $this->insertedContacts++;
            if (! $importDrafts || empty($candidate['valid_draft_lease'])) continue;
            $sourceKey = $spec['source_label'].'|'.$sheet['name'].'|'.$candidate['source_row'];
            $contractNumber = 'XL-'.strtoupper(substr(hash('sha256', $sourceKey), 0, 20));
            $lease = Lease::firstOrCreate(['company_id' => $company->id, 'contract_number' => $contractNumber], [
                'unit_id' => $unit->id, 'contact_id' => $contact->id,
                'starts_on' => $candidate['starts_on'], 'ends_on' => $candidate['ends_on'],
                'rent_amount' => $candidate['source_amount'], 'deposit' => $candidate['source_deposit'] ?: 0,
                'billing_cycle' => 'custom', 'status' => 'draft',
                'legacy_receipt_number' => $candidate['receipt_ref'] ?: null,
                'notes' => 'عقد تاريخي غير معتمد مستخرج من Excel؛ المبلغ كما ورد في المصدر، ودورية السداد غير مثبتة. المرجع: '.$sourceKey,
            ]);
            if ($lease->wasRecentlyCreated) $this->insertedDrafts++;
        }
    }

    private function flushRows(array &$pending): void
    {
        if (! $pending) return;
        $this->insertedRows += DB::table('import_rows')->insertOrIgnore($pending);
        $pending = [];
    }

    private function hasSpreadsheetError(array $cells): bool
    {
        foreach ($cells as $value) {
            if (is_string($value) && in_array(strtoupper(trim($value)), ['#VALUE!', '#REF!', '#DIV/0!', '#N/A', '#NAME?', '#NUM!'], true)) return true;
        }
        return false;
    }
}
