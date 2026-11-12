<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
 public function up():void {
  Schema::create('companies',function(Blueprint $t){
   $t->id();$t->string('name',160);$t->string('slug',100)->unique();$t->string('country_code',2)->default('SA');$t->string('currency',3)->default('SAR');$t->string('city',100)->nullable();$t->string('phone',40)->nullable();$t->boolean('is_active')->default(true);$t->timestamps();
  });
  Schema::create('users',function(Blueprint $t){
   $t->id();$t->foreignId('company_id')->nullable()->constrained()->nullOnDelete();$t->string('name');$t->string('email')->unique();$t->timestamp('email_verified_at')->nullable();$t->string('password');$t->string('role',30)->default('viewer');$t->boolean('is_active')->default(true);$t->rememberToken();$t->timestamps();$t->index(['company_id','role']);
  });
  Schema::create('password_reset_tokens',function(Blueprint $t){$t->string('email')->primary();$t->string('token');$t->timestamp('created_at')->nullable();});
  Schema::create('sessions',function(Blueprint $t){$t->string('id')->primary();$t->foreignId('user_id')->nullable()->index();$t->string('ip_address',45)->nullable();$t->text('user_agent')->nullable();$t->longText('payload');$t->integer('last_activity')->index();});
  Schema::create('properties',function(Blueprint $t){
   $t->id();$t->foreignId('company_id')->constrained()->cascadeOnDelete();$t->string('name');$t->string('category',30)->default('residential');$t->string('country_code',2);$t->string('city',100);$t->string('district',100)->nullable();$t->string('address')->nullable();$t->decimal('area',13,2)->nullable();$t->string('status',25)->default('active');$t->text('description')->nullable();$t->timestamps();$t->index(['company_id','city']);
  });
  Schema::create('contacts',function(Blueprint $t){
   $t->id();$t->foreignId('company_id')->constrained()->cascadeOnDelete();$t->string('name');$t->string('type',30)->default('tenant');$t->string('phone',40)->nullable();$t->string('email')->nullable();$t->string('city',100)->nullable();$t->string('country_code',2)->nullable();$t->text('notes')->nullable();$t->timestamps();$t->index(['company_id','type']);
  });
  Schema::create('units',function(Blueprint $t){
   $t->id();$t->foreignId('company_id')->constrained()->cascadeOnDelete();$t->foreignId('property_id')->constrained()->restrictOnDelete();$t->string('code',50);$t->string('type',35)->default('apartment');$t->integer('floor')->nullable();$t->unsignedSmallInteger('rooms')->nullable();$t->unsignedSmallInteger('bathrooms')->nullable();$t->decimal('area',12,2)->nullable();$t->decimal('monthly_rent',15,3)->default(0);$t->string('status',25)->default('available');$t->text('description')->nullable();$t->timestamps();$t->unique(['property_id','code']);$t->index(['company_id','status']);
  });
  Schema::create('leases',function(Blueprint $t){
   $t->id();$t->foreignId('company_id')->constrained()->cascadeOnDelete();$t->foreignId('unit_id')->constrained()->restrictOnDelete();$t->foreignId('contact_id')->constrained()->restrictOnDelete();$t->string('contract_number',90);$t->date('starts_on');$t->date('ends_on');$t->decimal('rent_amount',15,3);$t->decimal('deposit',15,3)->default(0);$t->string('billing_cycle',20)->default('monthly');$t->string('status',25)->default('draft');$t->text('notes')->nullable();$t->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();$t->timestamps();$t->unique(['company_id','contract_number']);$t->index(['company_id','status','ends_on']);
  });
  Schema::create('invoices',function(Blueprint $t){
   $t->id();$t->foreignId('company_id')->constrained()->cascadeOnDelete();$t->foreignId('lease_id')->nullable()->constrained()->restrictOnDelete();$t->foreignId('contact_id')->constrained()->restrictOnDelete();$t->string('number',90);$t->date('issued_on');$t->date('due_on');$t->decimal('amount',15,3);$t->string('status',30)->default('unpaid');$t->text('description')->nullable();$t->timestamps();$t->unique(['company_id','number']);$t->index(['company_id','status','due_on']);
  });
  Schema::create('payments',function(Blueprint $t){
   $t->id();$t->foreignId('company_id')->constrained()->cascadeOnDelete();$t->foreignId('invoice_id')->constrained()->restrictOnDelete();$t->decimal('amount',15,3);$t->date('paid_on');$t->string('method',30)->default('transfer');$t->string('reference',120)->nullable();$t->text('notes')->nullable();$t->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();$t->timestamps();$t->index(['company_id','paid_on']);
  });
  Schema::create('maintenance_tickets',function(Blueprint $t){
   $t->id();$t->foreignId('company_id')->constrained()->cascadeOnDelete();$t->foreignId('property_id')->constrained()->restrictOnDelete();$t->foreignId('unit_id')->nullable()->constrained()->nullOnDelete();$t->foreignId('contact_id')->nullable()->constrained()->nullOnDelete();$t->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();$t->string('title');$t->text('description')->nullable();$t->string('priority',20)->default('medium');$t->string('status',25)->default('open');$t->date('due_on')->nullable();$t->timestamp('closed_at')->nullable();$t->timestamps();$t->index(['company_id','status','due_on']);
  });
  Schema::create('contractors',function(Blueprint $t){
   $t->id();$t->foreignId('company_id')->constrained()->cascadeOnDelete();$t->string('name');$t->string('specialty',100)->nullable();$t->string('phone',40)->nullable();$t->string('email')->nullable();$t->text('notes')->nullable();$t->timestamps();
  });
  Schema::create('construction_projects',function(Blueprint $t){
   $t->id();$t->foreignId('company_id')->constrained()->cascadeOnDelete();$t->foreignId('property_id')->nullable()->constrained()->nullOnDelete();$t->foreignId('contact_id')->nullable()->constrained()->nullOnDelete();$t->string('code',70);$t->string('name');$t->string('type',40)->default('general');$t->string('location')->nullable();$t->decimal('budget',15,3)->default(0);$t->decimal('contract_value',15,3)->default(0);$t->date('starts_on')->nullable();$t->date('ends_on')->nullable();$t->unsignedTinyInteger('progress')->default(0);$t->string('status',25)->default('planned');$t->text('description')->nullable();$t->timestamps();$t->unique(['company_id','code']);$t->index(['company_id','status']);
  });
  Schema::create('project_tasks',function(Blueprint $t){
   $t->id();$t->foreignId('company_id')->constrained()->cascadeOnDelete();$t->foreignId('construction_project_id')->constrained()->restrictOnDelete();$t->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();$t->string('title');$t->text('description')->nullable();$t->date('due_on')->nullable();$t->string('priority',20)->default('medium');$t->string('status',25)->default('todo');$t->timestamps();$t->index(['company_id','status']);
  });
  Schema::create('project_expenses',function(Blueprint $t){
   $t->id();$t->foreignId('company_id')->constrained()->cascadeOnDelete();$t->foreignId('construction_project_id')->constrained()->restrictOnDelete();$t->foreignId('contractor_id')->nullable()->constrained()->nullOnDelete();$t->string('category',60)->default('materials');$t->decimal('amount',15,3);$t->date('spent_on');$t->text('description')->nullable();$t->timestamps();$t->index(['company_id','spent_on']);
  });
  Schema::create('leads',function(Blueprint $t){
   $t->id();$t->foreignId('company_id')->constrained()->cascadeOnDelete();$t->string('name');$t->string('phone',40)->nullable();$t->string('email')->nullable();$t->string('interest',60)->default('rent');$t->string('stage',30)->default('new');$t->decimal('estimated_value',15,3)->nullable();$t->date('next_follow_up')->nullable();$t->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();$t->text('notes')->nullable();$t->timestamps();$t->index(['company_id','stage']);
  });
  Schema::create('knowledge_entries',function(Blueprint $t){
   $t->id();$t->foreignId('company_id')->constrained()->cascadeOnDelete();$t->string('category',60)->default('general');$t->string('question');$t->text('answer');$t->text('keywords')->nullable();$t->boolean('is_published')->default(true);$t->timestamps();$t->index(['company_id','is_published']);
  });
  Schema::create('assistant_messages',function(Blueprint $t){
   $t->id();$t->foreignId('company_id')->constrained()->cascadeOnDelete();$t->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();$t->text('question');$t->text('answer');$t->string('intent',70)->default('unknown');$t->decimal('confidence',4,3)->default(0);$t->timestamps();$t->index(['company_id','created_at']);
  });
  Schema::create('activity_logs',function(Blueprint $t){
   $t->id();$t->foreignId('company_id')->nullable()->constrained()->nullOnDelete();$t->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();$t->string('action',40);$t->string('entity_type',100);$t->unsignedBigInteger('entity_id')->nullable();$t->string('summary')->nullable();$t->timestamps();$t->index(['company_id','created_at']);
  });
 }
 public function down():void {
  foreach (['activity_logs','assistant_messages','knowledge_entries','leads','project_expenses','project_tasks','construction_projects','contractors','maintenance_tickets','payments','invoices','leases','units','contacts','properties','sessions','password_reset_tokens','users','companies'] as $table) Schema::dropIfExists($table);
 }
};
