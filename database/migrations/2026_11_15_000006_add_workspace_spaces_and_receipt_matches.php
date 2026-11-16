<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('contacts',fn(Blueprint $table)=>$table->text('identity_reference')->nullable()->change());
        Schema::create('workspace_spaces', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('property_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('unit_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('kind',30);
            $table->string('name',120);
            $table->text('description')->nullable();
            $table->timestamps();
        });
        Schema::create('workspace_images', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('property_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('unit_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('workspace_space_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('path');
            $table->string('mime',40);
            $table->timestamps();
        });
        Schema::create('asab_receipt_matches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('history_id')->constrained('asab_financial_history')->cascadeOnDelete();
            $table->foreignId('lease_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('invoice_id')->nullable()->constrained()->nullOnDelete();
            $table->string('state',30);
            $table->timestamps();
            $table->unique(['company_id','history_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('asab_receipt_matches');
        Schema::dropIfExists('workspace_images');
        Schema::dropIfExists('workspace_spaces');
        // Keep the wider identity storage so rollback never truncates encrypted references.
    }
};
