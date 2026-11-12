<?php

use Illuminate\Support\Facades\Artisan;
Artisan::command('asab:about', function () {
    $this->info('ASAB SOFT | Property, leasing and construction platform (Saudi Arabia & Bahrain).');
});

Artisan::command('asab:reconcile-payments {companyId} {--apply}',function () {
    $companyId=(int)$this->argument('companyId');
    $company=\App\Models\Company::findOrFail($companyId);
    $rows=\App\Models\Payment::where('company_id',$companyId)->whereDoesntHave('ledgerEntry')->with('invoice.lease.unit')->get();
    $this->info('Company: '.$company->name.' | Payments missing ledger postings: '.$rows->count());
    if(!$this->option('apply')){
        $this->warn('DRY RUN. Nothing changed. Create cash/bank accounts, reconcile bank statements, then run again with --apply.');
        return;
    }
    foreach($rows as $p){
        $type=$p->method==='cash'?'cash':'bank';
        $accounts=\App\Models\FinancialAccount::where('company_id',$companyId)->where('type',$type)->where('is_active',true)->get();
        if($accounts->count()!==1){$this->error('Payment '.$p->id.': expected exactly one active '.$type.' account; found '.$accounts->count().'. Skipped.');continue;}
        \Illuminate\Support\Facades\DB::transaction(function () use($p,$companyId,$accounts){
            \App\Models\Payment::where('company_id',$companyId)->lockForUpdate()->findOrFail($p->id);
            $lease=$p->invoice?->lease;
            \App\Models\LedgerEntry::firstOrCreate(['source_payment_id'=>$p->id],[
                'company_id'=>$companyId,'financial_account_id'=>$accounts->first()->id,
                'property_id'=>$lease?->unit?->property_id,'unit_id'=>$lease?->unit_id,
                'contact_id'=>$p->invoice?->contact_id,'invoice_id'=>$p->invoice_id,
                'posted_on'=>$p->paid_on,'direction'=>'in','category'=>$lease?'rent':'general','amount'=>$p->amount,
                'bank_reference'=>$p->reference,'description'=>'ترحيل دفعة سابقة بعد تسوية: '.$p->id,
            ]);
        });
    }
    $this->info('Reconciliation completed. Verify balances in Reports.');
})->purpose('Preview or reconcile older invoice payments to the finance ledger. Apply only after review.');
