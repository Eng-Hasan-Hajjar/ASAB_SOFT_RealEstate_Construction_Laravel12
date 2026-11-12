<?php

namespace App\Console\Commands;

use App\Models\{Company, Contact, Lease, Property, Unit};
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

final class AsabSeedAudit extends Command
{
    protected $signature = 'asab:seed-audit {--slug=asab-pilot-client}';
    protected $description = 'Counts imported records and review rows without displaying personal data';

    public function handle(): int
    {
        $company = Company::where('slug', (string) $this->option('slug'))->first();
        if (! $company) {
            $this->error('Company not found.');
            return self::FAILURE;
        }
        $id = $company->id;
        $batchIds = DB::table('import_batches')->where('company_id', $id)->pluck('id');
        $this->table(['ASAB SOFT record', 'Count'], [
            ['Properties (including existing records)', Property::where('company_id', $id)->count()],
            ['Units (including existing records)', Unit::where('company_id', $id)->count()],
            ['Contacts (including existing records)', Contact::where('company_id', $id)->count()],
            ['Leases (including existing records)', Lease::where('company_id', $id)->count()],
            ['Private import batches', $batchIds->count()],
            ['Original Excel rows staged', DB::table('import_rows')->whereIn('import_batch_id', $batchIds)->count()],
            ['Rows flagged for review', DB::table('import_rows')->whereIn('import_batch_id', $batchIds)->where('state', 'flagged')->count()],
            ['Imported ledger entries', DB::table('ledger_entries')->where('company_id', $id)->count()],
        ]);
        $this->warn('Staged rows and draft leases are NOT verified accounting revenue.');
        return self::SUCCESS;
    }
}
