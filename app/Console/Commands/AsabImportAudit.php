<?php

namespace App\Console\Commands;

use App\Models\Company;
use App\Support\RentalSheetRows;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** Compare source row identities without disclosing tenant information. Read-only. */
final class AsabImportAudit extends Command
{
    protected $signature = 'asab:audit-import {--company=sss}';
    protected $description = 'Read-only check of all 14 Excel snapshots vs operational/staging data';

    public function handle(): int
    {
        $company = Company::where('slug', (string) $this->option('company'))->first();
        if (!$company) { $this->error('Company slug not found.'); return self::FAILURE; }
        foreach (['import_rows','import_batches','asab_rental_history','asab_financial_history'] as $t) {
            if (!Schema::hasTable($t)) { $this->error('Table '.$t.' missing. Install V4 migration first.'); return self::FAILURE; }
        }
        $root=storage_path('app/asab-private-import');
        if (!is_file($root.'/manifest.json')) { $this->error('Private manifest missing. Unzip PRIVATE data locally first.'); return self::FAILURE; }
        $manifest=json_decode((string)file_get_contents($root.'/manifest.json'),true);
        if (count($manifest['sources']??[])!==14) { $this->error('Expected 14 source workbooks.'); return self::FAILURE; }
        $id=(int)$company->id;
        $expected=['properties'=>0,'units'=>0,'archive'=>0,'rental'=>0,'finance'=>0];
        $actual=array_fill_keys(array_keys($expected),0);
        $properties=DB::table('properties')->where('company_id',$id)->pluck('id','name')->all();
        $units=[];
        foreach (DB::table('units')->where('company_id',$id)->get(['property_id','code']) as $u) $units[$u->property_id.'|'.$u->code]=true;
        $rentalKeys=array_fill_keys(DB::table('asab_rental_history')->where('company_id',$id)->pluck('source_key')->all(),true);
        $financeKeys=array_fill_keys(DB::table('asab_financial_history')->where('company_id',$id)->pluck('source_key')->all(),true);
        $missing=[];
        foreach ($manifest['sources'] as $spec) {
            $file=$root.'/'.(string)$spec['file'];
            if (!is_file($file)||!hash_equals((string)$spec['archive_sha256'],hash_file('sha256',$file))) {
                $this->error('Invalid or missing private source: '.basename($file)); return self::FAILURE;
            }
            $book=json_decode((string)gzdecode((string)file_get_contents($file)),true);
            if (count($book['sheets']??[])!==(int)$spec['sheet_count']) { $this->error('Source sheet count mismatch.');return self::FAILURE; }
            $name=(string)$spec['source_label'];
            $propertyFile=$spec['kind']==='property';
            if($propertyFile){$expected['properties']++;if(isset($properties[$name]))$actual['properties']++;else $this->note($missing,'Property: '.$name);}
            $batchIds=DB::table('import_batches')->where('company_id',$id)->where('source_filename',$name.'.xlsx')->pluck('id');
            $archived=[];
            if($batchIds->isNotEmpty())foreach(DB::table('import_rows')->whereIn('import_batch_id',$batchIds)->get(['source_sheet','row_number']) as $r){
                $archived[$r->source_sheet.'|'.$r->row_number]=true;
            }
            foreach($book['sheets'] as $sheet){
                $sheetName=(string)$sheet['name'];$isUnit=$propertyFile&&!empty($sheet['is_unit_sheet']);
                if($isUnit){$expected['units']++;if(isset($properties[$name])&&isset($units[$properties[$name].'|'.$sheetName]))$actual['units']++;else $this->note($missing,'Unit: '.$name.' / '.$sheetName);}
                $candidates=[];
                if($isUnit)foreach($sheet['candidates']??[] as $c){
                    $n=trim((string)($c['name']??''));if($n!==''&&mb_strlen($n)<=255)$candidates[(int)$c['source_row']]=true;
                }
                $interpreted=$isUnit?iterator_to_array(RentalSheetRows::explain($sheet,$candidates)):[];
                $bank=str_contains($sheetName,'بنكي');
                foreach($sheet['rows'] as $row){
                    $r=(int)$row['row'];$cells=$row['cells']??[];$seq=trim((string)($cells['A']??''));
                    $expected['archive']++;if(isset($archived[$sheetName.'|'.$r]))$actual['archive']++;
                    if(isset($interpreted[$r])){
                        $expected['rental']++;
                        $key=hash('sha256',$id.'|'.$name.'|'.$sheetName.'|'.$r.'|rental');
                        if(isset($rentalKeys[$key]))$actual['rental']++;else $this->note($missing,'Rental: '.$name.' / '.$sheetName.' row '.$r);
                    }
                    if($spec['kind']!=='income')continue;
                    $main=$bank?'F':'G';
                    if(preg_match('/^\d{1,5}$/',$seq)&&$this->positive($cells[$main]??null))$this->finance($expected,$actual,$financeKeys,$missing,$id,$name,$sheetName,$r,$main);
                    if(!$bank && $this->positive($cells['L']??null)&&$this->validMonthDay($cells['K']??null))$this->finance($expected,$actual,$financeKeys,$missing,$id,$name,$sheetName,$r,'L');
                    if($bank){
                        $auxLabel=trim((string)($cells['L']??''));
                        if($this->positive($cells['M']??null)&&$auxLabel!==''&&!preg_match('/^(الدخل|السحوبات|الصافي|الاجمالي|الإجمالي|المبلغ|البنك|الرصيد)/u',$auxLabel)){
                            $this->finance($expected,$actual,$financeKeys,$missing,$id,$name,$sheetName,$r,'M');
                        }
                    }
                }
            }
        }
        $table=[];$success=true;
        foreach($expected as $key=>$count){$ok=$actual[$key]===$count;$success=$success&&$ok;$table[]=[$key,$count,$actual[$key],$ok?'PASS':'MISSING'];}
        $this->table(['Source category','Expected','Found in database','Audit'],$table);
        $this->table(['Operational table','Count'],array_map(fn($t)=>[$t,DB::table($t)->where('company_id',$id)->count()],['contacts','leases','invoices','payments','ledger_entries']));
        foreach($missing as $example)$this->warn($example);
        $this->warn('Historical finance is NOT an approved payment, invoice or ledger entry. Separate matching is required.');
        $this->line($success?'SOURCE ROWS VERIFIED':'INCOMPLETE IMPORT. Do not claim full coverage.');
        return $success?self::SUCCESS:self::FAILURE;
    }
    private function note(array &$missing,string $message):void{if(count($missing)<5)$missing[]=$message;}
    private function positive(mixed $n):bool{
        $v=\App\Support\ExcelMoney::parse($n);
        return $v!==null && (float)$v>0;
    }
    private function validMonthDay(mixed $v):bool{
        if(!preg_match('/^(\d{1,2})[-\/]([0-9]{1,2})$/',trim((string)$v),$m))return false;
        return checkdate((int)$m[1],(int)$m[2],2026);
    }
    private function finance(array &$expected,array &$actual,array $keys,array &$missing,int $company,string $source,string $sheet,int $row,string $col):void{
        $expected['finance']++;
        $key=hash('sha256',$company.'|'.$source.'|'.$sheet.'|'.$row.'|financial:'.$col);
        if(isset($keys[$key]))$actual['finance']++;else $this->note($missing,'Financial: '.$source.' / '.$sheet.' row '.$row.' '.$col);
    }
}
