<?php
namespace Tests\Feature;

use App\Models\{Company, PropertyAccessGrant, Property, Unit, User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Covers the per-employee, per-property permission system: an admin can scope an employee to
 * specific properties and, independently per property, decide whether that employee may view
 * the property at all, view its sale price, view its rent price, and/or edit either price.
 *
 * The point of these tests is that enforcement happens on the server regardless of what the UI
 * would have shown or hidden: a crafted direct request must behave the same as using the app.
 */
class PropertyAccessPermissionsTest extends TestCase
{
    use RefreshDatabase;

    private function company(string $slug = 'alpha'): Company
    {
        return Company::create(['name' => 'Company ' . $slug, 'slug' => $slug, 'country_code' => 'SA', 'currency' => 'SAR', 'is_active' => true]);
    }

    private function admin(Company $company): User
    {
        return User::create(['company_id' => $company->id, 'name' => 'Admin', 'email' => uniqid('admin') . '@example.test', 'password' => 'S3curePassword!2026', 'role' => 'company_admin', 'is_active' => true]);
    }

    /** An "employee" subject to the new restriction: role=agent, scope_properties=true. */
    private function scopedEmployee(Company $company): User
    {
        return User::create(['company_id' => $company->id, 'name' => 'Employee', 'email' => uniqid('employee') . '@example.test', 'password' => 'S3curePassword!2026', 'role' => 'agent', 'is_active' => true, 'scope_properties' => true]);
    }

    private function property(Company $company, string $name = 'Building'): Property
    {
        return Property::create(['company_id' => $company->id, 'name' => $name, 'country_code' => 'SA', 'city' => 'Riyadh', 'category' => 'residential', 'status' => 'active']);
    }

    private function unit(Company $company, Property $property, array $overrides = []): Unit
    {
        return Unit::create(array_merge([
            'company_id' => $company->id, 'property_id' => $property->id, 'code' => 'U-' . uniqid(),
            'type' => 'apartment', 'monthly_rent' => 2500, 'sale_price' => 450000, 'status' => 'available',
        ], $overrides));
    }

    private function grant(User $user, Property $property, array $flags): PropertyAccessGrant
    {
        return PropertyAccessGrant::create(array_merge([
            'company_id' => $property->company_id, 'user_id' => $user->id, 'property_id' => $property->id,
            'can_view_property' => true, 'can_view_sale_price' => false, 'can_view_rent_price' => false, 'can_edit_prices' => false,
        ], $flags));
    }

    public function test_scoped_employee_without_any_grant_cannot_see_the_property_at_all(): void
    {
        $company = $this->company();
        $employee = $this->scopedEmployee($company);
        $hidden = $this->property($company, 'Hidden Tower');

        $this->actingAs($employee)->get('/manage/properties')->assertOk()->assertDontSee('Hidden Tower');
        $this->actingAs($employee)->get('/manage/properties/' . $hidden->id)->assertNotFound();
        $this->actingAs($employee)->get('/manage/properties/' . $hidden->id . '/preview')->assertNotFound();
        $this->actingAs($employee)->get('/manage/properties/' . $hidden->id . '/edit')->assertNotFound();
        $this->actingAs($employee)->get('/property-explorer/' . $hidden->id)->assertNotFound();
    }

    public function test_employee_can_see_property_details_without_seeing_any_price(): void
    {
        $company = $this->company();
        $employee = $this->scopedEmployee($company);
        $property = $this->property($company, 'Visible Villas');
        $unit = $this->unit($company, $property, ['listing_type' => 'both']);
        $this->grant($employee, $property, ['can_view_property' => true, 'can_view_sale_price' => false, 'can_view_rent_price' => false]);

        $rent = $company->money(2500);
        $sale = $company->money(450000);

        $propertyPage = $this->actingAs($employee)->get('/manage/properties/' . $property->id)->assertOk();
        $propertyPage->assertSee('Visible Villas')->assertDontSee($rent, false)->assertSee('غير مصرح');

        $unitPreview = $this->actingAs($employee)->get('/manage/units/' . $unit->id . '/preview')->assertOk();
        $unitPreview->assertDontSee($rent, false)->assertDontSee($sale, false)->assertSee('غير مصرح');

        // Server-side, not merely a hidden field: the raw JSON-free HTML response for this
        // employee must never contain either amount, however it's requested.
        $this->assertStringNotContainsString($rent, $unitPreview->getContent());
        $this->assertStringNotContainsString($sale, $unitPreview->getContent());
    }

    public function test_employee_can_see_rent_price_without_sale_price(): void
    {
        $company = $this->company();
        $employee = $this->scopedEmployee($company);
        $property = $this->property($company, 'Mixed Listing');
        $unit = $this->unit($company, $property, ['listing_type' => 'both']);
        $this->grant($employee, $property, ['can_view_sale_price' => false, 'can_view_rent_price' => true]);

        $rent = $company->money(2500);
        $sale = $company->money(450000);

        $response = $this->actingAs($employee)->get('/manage/units/' . $unit->id . '/preview')->assertOk();
        $response->assertSee($rent, false);
        $this->assertStringNotContainsString($sale, $response->getContent());
    }

    public function test_employee_without_edit_permission_cannot_change_price_via_direct_request(): void
    {
        $company = $this->company();
        $employee = $this->scopedEmployee($company);
        $property = $this->property($company);
        $unit = $this->unit($company, $property, ['monthly_rent' => 2500]);
        $this->grant($employee, $property, ['can_view_rent_price' => true, 'can_edit_prices' => false]);

        $this->actingAs($employee)->put('/manage/units/' . $unit->id, [
            'property_id' => $property->id, 'code' => $unit->code, 'type' => 'apartment',
            'monthly_rent' => 9999, 'status' => 'available', 'description' => 'Updated via a crafted request',
        ])->assertRedirect();

        $unit->refresh();
        $this->assertEquals(2500.0, (float) $unit->monthly_rent);
        $this->assertEquals('Updated via a crafted request', $unit->description);
    }

    public function test_employee_with_edit_permission_can_change_price(): void
    {
        $company = $this->company();
        $employee = $this->scopedEmployee($company);
        $property = $this->property($company);
        $unit = $this->unit($company, $property, ['monthly_rent' => 2500]);
        $this->grant($employee, $property, ['can_view_rent_price' => true, 'can_edit_prices' => true]);

        $this->actingAs($employee)->put('/manage/units/' . $unit->id, [
            'property_id' => $property->id, 'code' => $unit->code, 'type' => 'apartment',
            'monthly_rent' => 3100, 'status' => 'available',
        ])->assertRedirect();

        $this->assertEquals(3100.0, (float) $unit->refresh()->monthly_rent);
    }

    public function test_employee_cannot_open_or_modify_a_unit_outside_their_scope_via_direct_request(): void
    {
        $company = $this->company();
        $employee = $this->scopedEmployee($company);
        $granted = $this->property($company, 'Granted Property');
        $outOfScope = $this->property($company, 'Other Property');
        $this->grant($employee, $granted, ['can_view_rent_price' => true, 'can_edit_prices' => true]);
        $foreignUnit = $this->unit($company, $outOfScope);

        $this->actingAs($employee)->get('/manage/units/' . $foreignUnit->id . '/preview')->assertNotFound();
        $this->actingAs($employee)->get('/manage/units/' . $foreignUnit->id . '/edit')->assertNotFound();
        $this->actingAs($employee)->put('/manage/units/' . $foreignUnit->id, [
            'property_id' => $outOfScope->id, 'code' => $foreignUnit->code, 'type' => 'apartment',
            'monthly_rent' => 1, 'status' => 'available',
        ])->assertNotFound();
        $this->assertEquals(2500.0, (float) $foreignUnit->refresh()->monthly_rent);

        // Cannot create a brand-new unit under the out-of-scope property either, even though the
        // route itself is reachable (the property_id is simply not in their granted list).
        $this->actingAs($employee)->post('/manage/units', [
            'property_id' => $outOfScope->id, 'code' => 'NEW-1', 'type' => 'apartment',
            'monthly_rent' => 1200, 'status' => 'available',
        ])->assertSessionHasErrors('property_id');
        $this->assertDatabaseMissing('units', ['code' => 'NEW-1']);
    }

    public function test_scoped_employee_listing_only_shows_granted_properties_and_masks_rent_in_the_table(): void
    {
        $company = $this->company();
        $employee = $this->scopedEmployee($company);
        $granted = $this->property($company, 'Allowed Building');
        $hidden = $this->property($company, 'Forbidden Building');
        $this->grant($employee, $granted, ['can_view_rent_price' => false]);
        $this->unit($company, $granted, ['code' => 'A-1', 'monthly_rent' => 4000]);
        $this->unit($company, $hidden, ['code' => 'B-1', 'monthly_rent' => 5000]);

        $response = $this->actingAs($employee)->get('/manage/units')->assertOk();
        $response->assertSee('Allowed Building')->assertDontSee('Forbidden Building');
        $this->assertStringNotContainsString($company->money(4000), $response->getContent());
        $this->assertStringNotContainsString($company->money(5000), $response->getContent());
    }

    public function test_marketplace_edit_cannot_change_sale_price_via_direct_request_without_edit_permission(): void
    {
        $company = $this->company();
        $employee = $this->scopedEmployee($company);
        $property = $this->property($company);
        $property->update(['is_published' => false]);
        $unit = $this->unit($company, $property, ['listing_type' => 'sale', 'sale_price' => 100000, 'is_listed' => true]);
        $this->grant($employee, $property, ['can_view_sale_price' => true, 'can_edit_prices' => false]);

        $this->actingAs($employee)->put('/listings/' . $property->id . '/units/' . $unit->id, [
            'listing_type' => 'sale', 'sale_price' => 999999,
        ])->assertRedirect();

        $this->assertEquals(100000.0, (float) $unit->refresh()->sale_price);
    }

    public function test_marketplace_edit_page_404s_for_a_property_outside_scope(): void
    {
        $company = $this->company();
        $employee = $this->scopedEmployee($company);
        $outOfScope = $this->property($company);

        $this->actingAs($employee)->get('/listings/' . $outOfScope->id . '/edit')->assertNotFound();
    }

    public function test_company_admin_and_manager_always_keep_full_access_even_if_scope_flag_is_set(): void
    {
        $company = $this->company();
        // An admin/manager account is never restricted, whatever scope_properties says — this
        // flag is meant to restrict employees, not the people configuring their access.
        $admin = User::create(['company_id' => $company->id, 'name' => 'Admin', 'email' => uniqid('admin') . '@example.test', 'password' => 'S3curePassword!2026', 'role' => 'company_admin', 'is_active' => true, 'scope_properties' => true]);
        $property = $this->property($company, 'Unrestricted For Admins');
        $unit = $this->unit($company, $property, ['monthly_rent' => 7000]);

        $response = $this->actingAs($admin)->get('/manage/units/' . $unit->id . '/preview')->assertOk();
        $response->assertSee($company->money(7000), false);

        $this->actingAs($admin)->put('/manage/units/' . $unit->id, [
            'property_id' => $property->id, 'code' => $unit->code, 'type' => 'apartment',
            'monthly_rent' => 7200, 'status' => 'available',
        ])->assertRedirect();
        $this->assertEquals(7200.0, (float) $unit->refresh()->monthly_rent);
    }

    public function test_unscoped_employee_keeps_seeing_everything_exactly_as_before(): void
    {
        // Backward compatibility: scope_properties defaults to false, so an existing agent with
        // no grants configured at all must keep full visibility, unchanged by this feature.
        $company = $this->company();
        $agent = User::create(['company_id' => $company->id, 'name' => 'Legacy Agent', 'email' => uniqid('legacy') . '@example.test', 'password' => 'S3curePassword!2026', 'role' => 'agent', 'is_active' => true]);
        $property = $this->property($company, 'Legacy Visible Property');
        $unit = $this->unit($company, $property, ['monthly_rent' => 1800]);

        $response = $this->actingAs($agent)->get('/manage/units/' . $unit->id . '/preview')->assertOk();
        $response->assertSee($company->money(1800), false);
        $this->actingAs($agent)->get('/manage/properties')->assertOk()->assertSee('Legacy Visible Property');
    }

    public function test_admin_can_grant_and_revoke_permissions_from_the_dashboard(): void
    {
        $company = $this->company();
        $admin = $this->admin($company);
        $employee = User::create(['company_id' => $company->id, 'name' => 'New Hire', 'email' => uniqid('hire') . '@example.test', 'password' => 'S3curePassword!2026', 'role' => 'agent', 'is_active' => true]);
        $propertyOne = $this->property($company, 'Grant Me');
        $propertyTwo = $this->property($company, 'Not This One');

        $this->actingAs($admin)->put('/staff/' . $employee->id . '/permissions', [
            'role' => 'agent', 'scope_properties' => '1',
            'grants' => [
                ['property_id' => $propertyOne->id, 'can_view_property' => '1', 'can_view_rent_price' => '1'],
                ['property_id' => $propertyTwo->id],
            ],
        ])->assertRedirect();

        $employee->refresh();
        $this->assertTrue((bool) $employee->scope_properties);
        $this->assertDatabaseHas('property_access_grants', ['user_id' => $employee->id, 'property_id' => $propertyOne->id, 'can_view_property' => 1, 'can_view_rent_price' => 1]);
        $this->assertDatabaseMissing('property_access_grants', ['user_id' => $employee->id, 'property_id' => $propertyTwo->id]);

        $this->unit($company, $propertyOne, ['monthly_rent' => 1500]);
        $this->actingAs($employee)->get('/manage/properties')->assertOk()->assertSee('Grant Me')->assertDontSee('Not This One');
    }

    public function test_scoped_employee_cannot_create_a_brand_new_property(): void
    {
        $company = $this->company();
        $employee = $this->scopedEmployee($company);

        $this->actingAs($employee)->get('/manage/properties/create')->assertForbidden();
        $this->actingAs($employee)->post('/manage/properties', [
            'name' => 'Sneaky New Building', 'country_code' => 'SA', 'city' => 'Riyadh', 'category' => 'residential', 'status' => 'active',
        ])->assertForbidden();
        $this->assertDatabaseMissing('properties', ['name' => 'Sneaky New Building']);
    }
}
