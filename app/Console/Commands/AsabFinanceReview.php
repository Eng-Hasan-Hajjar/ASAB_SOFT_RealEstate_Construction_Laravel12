<?php
namespace App\Console\Commands;

use App\Models\{AsabFinancialHistory,Company};
use App\Services\AsabFinancePosting;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use RuntimeException;

final class AsabFinanceReview extends Command
{
    protected $signature='asab:finance-review {--company= : Existing company slug} {--export : Export a private approval template} {--apply : Post explicitly approved rows}';
    protected $description='Review private Excel rows and post approved transactions with provenance';

    private const HEADER=['source_key','flow','channel','source_amount','source_date','invoice_id','financial_account_id','posted_on','category','reviewer','evidence_reference','approved'];

    public function handle(AsabFinancePosting $posting): int
    {
        $slug=trim((string)$this->option('company'));
        $company=Company::where('slug',$slug)->first();
        if(!$company||!preg_match('/^[a-zA-Z0-9_-]+$/D',$company->slug)){$this->error('حدد رمز الشركة عبر --company.');return self::FAILURE;}
        if($this->option('export')&&$this->option('apply')){$this->error('نفّذ التصدير والترحيل كلًا على حدة.');return self::FAILURE;}
        $path=storage_path('app/asab-private-import/finance_approved_'.$company->slug.'.csv');
        try{
            if($this->option('export'))return $this->export($company,$path);
            $input=$this->read($path);
            if(!$input){$this->info('لا توجد صفوف معتمدة.');return self::SUCCESS;}
            $apply=(bool)$this->option('apply');
            $made=0;$skipped=0;
            DB::transaction(function()use($input,$company,$apply,$posting,&$made,&$skipped){
                foreach($input as $line=>$row){
                    try{
                        if($posting->settle($company->id,$row['source_key'],$row,$apply))$made++;else $skipped++;
                    }catch(RuntimeException $e){throw new RuntimeException('CSV '.$line.' · '.$e->getMessage(),0,$e);}
                }
            });
            $this->info($apply?"مرحّل: $made · مرحّل سابقًا: $skipped":"صفوف صالحة فرديًا: $made · مرحّل سابقًا: $skipped · معاينة بلا كتابة");
            if(!$apply)$this->line('الرصيد التراكمي لصفوف الفاتورة نفسها يُعاد التحقق منه عند التطبيق، وتُلغى الدفعة الجماعية كاملة إذا فشل أحد الصفوف.');
            return self::SUCCESS;
        }catch(\Throwable $e){$this->error($e->getMessage());return self::FAILURE;}
    }

    private function export(Company $company,string $path): int
    {
        if(!is_dir(dirname($path))&&!mkdir(dirname($path),0700,true))throw new RuntimeException('تعذر إنشاء المجلد الخاص.');
        if(is_file($path))throw new RuntimeException('ملف الموافقات موجود ولن يُستبدل.');
        $handle=fopen($path,'xb');
        if(!$handle)throw new RuntimeException('تعذر إنشاء ملف الموافقات.');
        try{
            fwrite($handle,"\xEF\xBB\xBF");
            fputcsv($handle,self::HEADER,',','"','');
            $matches=DB::table('asab_receipt_matches')->where('company_id',$company->id)->where('state','ready_for_review')->pluck('invoice_id','history_id');
            foreach(AsabFinancialHistory::where('company_id',$company->id)->orderBy('id')->cursor() as $h){
                $date=$h->event_on?->toDateString()??'';
                fputcsv($handle,[$h->source_key,$h->flow,$h->channel,$h->source_amount,$date,$matches[$h->id]??'','',$date,'','','','NO'],',','"','');
            }
        }finally{fclose($handle);}
        $this->info($path);
        return self::SUCCESS;
    }

    private function read(string $path):array
    {
        if(!is_file($path))throw new RuntimeException('ملف الموافقات غير موجود. نفّذ --export أولًا.');
        $handle=fopen($path,'rb');$rows=[];$keys=[];
        try{
            $header=fgetcsv($handle,0,',','"','');
            if(!$header)throw new RuntimeException('ملف الموافقات فارغ.');
            $header[0]=preg_replace('/^\xEF\xBB\xBF/','',$header[0]);
            if($header!==self::HEADER)throw new RuntimeException('عناوين CSV غير مطابقة.');
            for($n=2;($cells=fgetcsv($handle,0,',','"',''))!==false;$n++){
                if($cells===[null])continue;
                if(count($cells)!==count($header))throw new RuntimeException('عدد أعمدة غير مطابق في CSV '.$n);
                $row=array_map(fn($cell)=>trim((string)$cell),array_combine($header,$cells));
                if(strtoupper($row['approved'])!=='YES')continue;
                if(isset($keys[$row['source_key']]))throw new RuntimeException('صف مصدر مكرر في ملف الاعتماد.');
                $keys[$row['source_key']]=true;$rows[$n]=$row;
            }
        }finally{fclose($handle);}
        return $rows;
    }
}
