<?php
namespace Database\Seeders;

use Illuminate\Database\Seeder;
use RuntimeException;

final class AsabExcelReceiptsSeeder extends Seeder
{
    public function run(): void
    {
        $slug=trim((string)env('ASAB_CLIENT_COMPANY_SLUG',''));
        if($slug==='') throw new RuntimeException('Set ASAB_CLIENT_COMPANY_SLUG or use asab:seed-receipts --company=SLUG --apply.');
        if(!$this->command || $this->command->call('asab:seed-receipts',['--company'=>$slug,'--apply'=>true])!==0)
            throw new RuntimeException('The Excel receipt seeder did not complete.');
    }
}
