<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up():void {
        Schema::create('asab_verified_finance_postings',function(Blueprint $table):void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('financial_history_id')->constrained('asab_financial_history')->restrictOnDelete();
            $table->char('source_key',64);
            $table->foreignId('payment_id')->nullable()->constrained('payments')->restrictOnDelete();
            $table->foreignId('ledger_entry_id')->constrained('ledger_entries')->restrictOnDelete();
            $table->string('approved_by',120);
            $table->string('evidence_reference',250);
            $table->timestamps();
            $table->unique(['company_id','source_key'],'asab_posting_source_unique');
        });
    }
    public function down():void {Schema::dropIfExists('asab_verified_finance_postings');}
};
