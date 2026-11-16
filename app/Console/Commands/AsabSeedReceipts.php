<?php
namespace App\Console\Commands;

use App\Models\Company;
use App\Services\{AsabActualExcelImporter,AsabReceiptMatcher};
use Illuminate\Console\Command;

final class AsabSeedReceipts extends Command
{
    protected $signature='asab:seed-receipts {--company= : Existing Saudi company slug} {--apply : Seed historical cash/bank receipts and store matching suggestions}';
    protected $description='Seed only receipts from the original private Excel snapshots and suggest contract/invoice matches without posting accounting entries';

    public function handle(AsabActualExcelImporter $importer,AsabReceiptMatcher $matcher): int
    {
        $slug=trim((string)$this->option('company'));
        $company=$slug===''?null:Company::where('slug',$slug)->first();
        if (!$company) {$this->error('اختر رمز شركة موجودة عبر --company.');return self::FAILURE;}
        $apply=(bool)$this->option('apply');
        try {
            $counts=$importer->receipts($company,$apply);
            $this->table(['المصدر','عدد صفوف Excel'],array_map(fn($key,$n)=>[$key,$n],array_keys($counts),$counts));
            $matches=$matcher->execute($company,$apply);
            $this->table(['مطابقة السجلات المخزنة','العدد'],array_map(fn($key,$n)=>[$key,$n],array_keys($matches),$matches));
            $this->info($apply?'تم حفظ الدفعات المصدرية واقتراحات الربط. الترحيل المحاسبي يتم من مسار اعتماد الدفعات.':'معاينة فقط. لم تتغير قاعدة البيانات.');
        }catch(\Throwable $e){$this->error($e->getMessage());return self::FAILURE;}
        return self::SUCCESS;
    }
}
