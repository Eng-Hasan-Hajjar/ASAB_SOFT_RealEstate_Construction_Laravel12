<?php
namespace App\Console\Commands;

use App\Models\Company;
use App\Services\AsabActualExcelImporter;
use Illuminate\Console\Command;
use Illuminate\Database\MultipleRecordsFoundException;
use Illuminate\Database\RecordsNotFoundException;

final class AsabInterpretRentals extends Command
{
    protected $signature='asab:interpret-rentals {--company= : Saudi pilot company slug} {--apply : Persist the historical interpretation}';
    protected $description='Interpret grouped tenant rows within each original pilot unit sheet; does not post payments or change occupancy';

    public function handle(AsabActualExcelImporter $importer):int
    {
        $slug=trim((string)$this->option('company'));
        $company=$slug!==''?Company::where('slug',$slug)->first():null;
        if(!$company){$this->error('حدد شركة الاستيراد عبر --company.');return self::FAILURE;}
        try{
            $apply=(bool)$this->option('apply');
            $counts=$importer->repairRentals($company,$apply);
            $this->table(['المؤشر','العدد'],array_map(fn($key,$value)=>[$key,$value],array_keys($counts),$counts));
            $this->info($apply?'تم تحديث سجل الإيجارات التاريخي فقط. لم تتغير المدفوعات المعتمدة أو العقود.':'معاينة فقط. لم تتغير البيانات.');
            return self::SUCCESS;
        }catch(MultipleRecordsFoundException|RecordsNotFoundException){
            $this->error('لا يوجد تطابق فريد لاسم أحد العقارات الأصلية ضمن هذه الشركة. تحقق من الشركة وأسماء العقارات.');
        }catch(\Throwable $e){ $this->error('تعذر تحديث السجل. شغّل الترحيلات وتأكد من اكتمال الاستيراد والأرشيف الخاص. '.($e instanceof \RuntimeException && get_class($e)===\RuntimeException::class?$e->getMessage():'')); }
        return self::FAILURE;
    }
}
