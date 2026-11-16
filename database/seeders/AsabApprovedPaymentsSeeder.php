<?php
namespace Database\Seeders;

use Illuminate\Database\Seeder;
use RuntimeException;

final class AsabApprovedPaymentsSeeder extends Seeder
{
    // Explicitly invoked only after reconciling the Excel source and marking approvals.
    public function run(): void
    {
        $slug=trim((string)env('ASAB_CLIENT_COMPANY_SLUG',''));
        if($slug==='') throw new RuntimeException('Set ASAB_CLIENT_COMPANY_SLUG or use asab:finance-review --company=SLUG --apply.');
        if(!$this->command || $this->command->call('asab:finance-review',['--company'=>$slug,'--apply'=>true])!==0)
            throw new RuntimeException('Verified Excel payments did not complete.');
    }
}
