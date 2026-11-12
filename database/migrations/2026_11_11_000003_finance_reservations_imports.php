<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
 public function up(): void {
  Schema::table('invoices', function(Blueprint $t){$t->date('period_start')->nullable();$t->date('period_end')->nullable();$t->string('origin',35)->default('manual');$t->unique(['lease_id','period_start'],'invoice_lease_period_unique');});
  Schema::table('contacts', function(Blueprint $t){$t->string('nationality',100)->nullable();$t->string('identity_reference',120)->nullable();});
  Schema::table('leases', function(Blueprint $t){$t->decimal('agent_fee',15,3)->default(0);$t->string('legacy_receipt_number',120)->nullable();});
  Schema::create('financial_accounts',function(Blueprint $t){
   $t->id();$t->foreignId('company_id')->constrained()->cascadeOnDelete();$t->string('name',150);$t->enum('type',['cash','bank']);$t->string('bank_name',100)->nullable();$t->string('currency',3);$t->decimal('opening_balance',18,3)->default(0);$t->boolean('is_active')->default(true);$t->timestamps();$t->unique(['company_id','name']);
  });
  Schema::create('ledger_entries',function(Blueprint $t){
   $t->id();$t->foreignId('company_id')->constrained()->cascadeOnDelete();$t->foreignId('financial_account_id')->constrained()->restrictOnDelete();$t->foreignId('property_id')->nullable()->constrained()->nullOnDelete();$t->foreignId('unit_id')->nullable()->constrained()->nullOnDelete();$t->foreignId('contact_id')->nullable()->constrained()->nullOnDelete();$t->foreignId('invoice_id')->nullable()->constrained()->nullOnDelete();$t->foreignId('source_payment_id')->nullable()->unique()->constrained('payments')->restrictOnDelete();$t->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
   $t->date('posted_on');$t->enum('direction',['in','out']);$t->string('category',45);$t->decimal('amount',18,3);$t->string('voucher_number',120)->nullable();$t->string('bank_reference',120)->nullable();$t->string('transfer_group',70)->nullable();$t->string('description',2000)->nullable();$t->timestamps();$t->index(['company_id','posted_on']);$t->index(['company_id','category']);
  });
  Schema::create('reservations',function(Blueprint $t){
   $t->id();$t->foreignId('company_id')->constrained()->cascadeOnDelete();$t->foreignId('unit_id')->constrained()->restrictOnDelete();$t->foreignId('contact_id')->constrained()->restrictOnDelete();$t->string('reference',80);$t->date('check_in');$t->date('check_out');$t->decimal('total_price',15,3);$t->string('status',24)->default('pending');$t->text('notes')->nullable();$t->timestamps();$t->unique(['company_id','reference']);$t->index(['company_id','unit_id','check_in','check_out']);
  });
  Schema::create('import_batches',function(Blueprint $t){
   $t->id();$t->foreignId('company_id')->constrained()->cascadeOnDelete();$t->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();$t->string('source_filename',255);$t->enum('profile',['unit_history','cash_income','bank_income'])->default('unit_history');$t->string('property_label')->nullable();$t->string('unit_label')->nullable();$t->string('status',25)->default('preview');$t->unsignedInteger('rows_total')->default(0);$t->unsignedInteger('rows_flagged')->default(0);$t->timestamps();
  });
  Schema::create('import_rows',function(Blueprint $t){
   $t->id();$t->foreignId('import_batch_id')->constrained()->cascadeOnDelete();$t->unsignedInteger('row_number');$t->string('source_sheet',120)->default('CSV');$t->longText('payload');$t->string('state',25)->default('pending_review');$t->text('review_note')->nullable();$t->timestamps();$t->unique(['import_batch_id','source_sheet','row_number'],'batch_sheet_row_unique');
  });
 }
 public function down(): void {
  foreach(['import_rows','import_batches','reservations','ledger_entries','financial_accounts'] as $table)Schema::dropIfExists($table);
  Schema::table('invoices',function(Blueprint $t){$t->dropUnique('invoice_lease_period_unique');$t->dropColumn(['period_start','period_end','origin']);});
  Schema::table('leases',fn(Blueprint $t)=>$t->dropColumn(['agent_fee','legacy_receipt_number']));
  Schema::table('contacts',fn(Blueprint $t)=>$t->dropColumn(['nationality','identity_reference']));
 }
};
