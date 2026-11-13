<?php

namespace Database\Seeders;

use App\Models\Company;
use App\Services\AsabActualExcelImporter;
use Illuminate\Database\Seeder;
use RuntimeException;

/**
 * Database seeder for the first client's actual 14 Excel source snapshots.
 * Default company slug may be set in .env; no user or company credentials created.
 * Prefer the --company option of the asab:import-actual Artisan command.
 */
final class AsabActualClientDataSeeder extends Seeder
{
    public function run(): void
    {
        $slug = trim((string) env('ASAB_CLIENT_COMPANY_SLUG', ''));
        if ($slug === '') throw new RuntimeException('Set ASAB_CLIENT_COMPANY_SLUG to an existing company slug, or run asab:import-actual --company=SLUG.');
        $company = Company::where('slug', $slug)->first();
        if (! $company) throw new RuntimeException('Company does not exist: '.$slug);
        $counts = app(AsabActualExcelImporter::class)->execute($company);
        foreach ($counts as $key => $number) $this->command?->line($key.': '.$number);
    }
}
