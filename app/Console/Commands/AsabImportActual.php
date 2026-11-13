<?php

namespace App\Console\Commands;

use App\Models\Company;
use App\Services\AsabActualExcelImporter;
use Illuminate\Console\Command;
use Throwable;

final class AsabImportActual extends Command
{
    protected $signature = 'asab:import-actual {--company= : Company slug (for example sss or asab-pilot-client)} {--dry-run : Read manifest only, never change the database} {--no-drafts : Do not create the small set of unverified draft leases}';
    protected $description = 'Import all 14 private Excel snapshots into actual company tables and historical screens; does NOT post unverified accounting revenue';

    public function handle(AsabActualExcelImporter $importer): int
    {
        $slug = trim((string) ($this->option('company') ?: env('ASAB_CLIENT_COMPANY_SLUG', 'asab-pilot-client')));
        $company = Company::where('slug', $slug)->first();
        if (! $company) {
            $this->error('Company not found: '.$slug.' . Select an existing company slug from the companies table.');
            return self::FAILURE;
        }
        $this->info('ASAB SOFT | Target company #'.$company->id.' / '.$company->name.' ('.$slug.')');
        $this->warn('Imported historical amounts are unverified and NEVER included in posted finance balances.');
        try {
            $counts = $importer->execute($company, (bool) $this->option('dry-run'), ! $this->option('no-drafts'));
        } catch (Throwable $e) {
            $this->error($e->getMessage());
            return self::FAILURE;
        }
        $this->table(['Section', $this->option('dry-run') ? 'Source count / estimate' : 'New records'],
            array_map(static fn ($key, $n) => [$key, number_format($n)], array_keys($counts), $counts));
        if ($this->option('dry-run')) $this->warn('DRY RUN - no data was written.');
        else $this->info('Import finished. Visit /manage/properties, /manage/units, /manage/contacts, /asab/history/rentals and /asab/history/finance.');
        return self::SUCCESS;
    }
}
