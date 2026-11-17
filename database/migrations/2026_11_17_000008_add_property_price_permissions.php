<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Adds: (1) a rent periodicity field on units (daily/monthly/yearly) alongside the existing
// monthly_rent and sale_price columns, and (2) a per-employee, per-property access-grant table
// that lets a company admin/manager restrict a specific employee to specific properties and
// decide — per property — whether that employee may view the property at all, view its sale
// price, view its rent price, and/or edit either price. Nothing here changes behaviour for an
// existing user: `users.scope_properties` defaults to false, so every existing account keeps
// seeing everything it could see before this migration, exactly as before.
return new class extends Migration {
    public function up(): void
    {
        Schema::table('units', function (Blueprint $t) {
            // Existing rows are all implicitly "monthly" (the column was literally named
            // monthly_rent), so defaulting new rows to 'monthly' keeps old data meaningful
            // without requiring anyone to re-enter anything.
            $t->string('rent_period', 10)->nullable()->default('monthly')->after('sale_price');
        });

        Schema::table('users', function (Blueprint $t) {
            // Opt-in flag: when false (the default for every current row), a user's visibility
            // is governed only by the existing role system, exactly as it works today. An admin
            // turns this on for a specific employee to put them under the new per-property grants
            // below. company_admin/manager (and the platform owner) always keep full access,
            // regardless of this flag — enforced in App\Support\PropertyAccess, not only here.
            $t->boolean('scope_properties')->default(false)->after('role');
        });

        Schema::create('property_access_grants', function (Blueprint $t) {
            $t->id();
            $t->foreignId('company_id')->constrained()->cascadeOnDelete();
            $t->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $t->foreignId('property_id')->constrained('properties')->cascadeOnDelete();
            // Whether the employee can see this property (and its units) at all.
            $t->boolean('can_view_property')->default(true);
            // Independent, per-property price visibility — an employee can be allowed to see
            // the rent price without the sale price, or vice versa, or neither.
            $t->boolean('can_view_sale_price')->default(false);
            $t->boolean('can_view_rent_price')->default(false);
            // Whether the employee may change either price for this property's units.
            $t->boolean('can_edit_prices')->default(false);
            $t->timestamps();
            $t->unique(['user_id', 'property_id']);
            $t->index(['company_id', 'property_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('property_access_grants');
        Schema::table('users', fn (Blueprint $t) => $t->dropColumn(['scope_properties']));
        Schema::table('units', fn (Blueprint $t) => $t->dropColumn(['rent_period']));
    }
};
