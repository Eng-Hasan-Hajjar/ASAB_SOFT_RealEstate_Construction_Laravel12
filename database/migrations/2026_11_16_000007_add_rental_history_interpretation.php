<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('asab_rental_history', function (Blueprint $table): void {
            $table->unsignedInteger('tenant_origin_row')->nullable();
            $table->string('entry_kind',24)->nullable();
            $table->index(['company_id','entry_kind'], 'asab_history_kind_idx');
        });
    }

    public function down(): void
    {
        Schema::table('asab_rental_history', function (Blueprint $table): void {
            $table->dropIndex('asab_history_kind_idx');
            $table->dropColumn(['tenant_origin_row','entry_kind']);
        });
    }
};
