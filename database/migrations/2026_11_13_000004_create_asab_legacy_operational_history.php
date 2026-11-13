<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('asab_rental_history', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('property_id')->constrained()->restrictOnDelete();
            $table->foreignId('unit_id')->constrained()->restrictOnDelete();
            $table->foreignId('contact_id')->nullable()->constrained()->nullOnDelete();
            $table->char('source_key', 64);
            $table->string('source_file', 180);
            $table->string('source_sheet', 140);
            $table->unsignedInteger('source_row');
            $table->string('sequence_label', 40)->nullable();
            $table->string('tenant_label', 255)->nullable();
            $table->string('reference', 120)->nullable();
            $table->string('date_from_source', 100)->nullable();
            $table->string('date_to_source', 100)->nullable();
            $table->date('starts_on')->nullable();
            $table->date('ends_on')->nullable();
            $table->decimal('source_amount', 18, 3)->nullable();
            $table->decimal('source_deposit', 18, 3)->nullable();
            $table->string('record_type', 25)->default('period');
            $table->string('review_status', 30)->default('needs_review');
            $table->text('memo')->nullable();
            $table->longText('encrypted_source');
            $table->timestamps();
            $table->unique(['company_id', 'source_key'], 'asab_rental_source_unique');
            $table->index(['company_id', 'property_id', 'unit_id'], 'asab_rental_company_unit_idx');
        });

        Schema::create('asab_financial_history', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('property_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('unit_id')->nullable()->constrained()->nullOnDelete();
            $table->char('source_key', 64);
            $table->string('source_file', 180);
            $table->string('source_sheet', 140);
            $table->unsignedInteger('source_row');
            $table->string('source_column', 3);
            $table->string('source_date_label', 100)->nullable();
            $table->date('event_on')->nullable();
            $table->string('channel', 25);
            $table->string('flow', 12); // in | out | review (not an accounting posting)
            $table->string('party_name', 255)->nullable();
            $table->string('property_label', 160)->nullable();
            $table->string('unit_label', 90)->nullable();
            $table->string('voucher_number', 120)->nullable();
            $table->decimal('source_amount', 18, 3);
            $table->string('bank_name', 100)->nullable();
            $table->string('review_status', 30)->default('unverified');
            $table->text('memo')->nullable();
            $table->longText('encrypted_source');
            $table->timestamps();
            $table->unique(['company_id', 'source_key'], 'asab_financial_source_unique');
            $table->index(['company_id', 'channel', 'flow'], 'asab_financial_flow_idx');
            $table->index(['company_id', 'event_on'], 'asab_financial_date_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('asab_financial_history');
        Schema::dropIfExists('asab_rental_history');
    }
};
