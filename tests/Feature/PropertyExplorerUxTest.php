<?php
namespace Tests\Feature;

use App\Models\{Company,Contact,Lease,PropertyAccessGrant,Reservation,Property,Unit,User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Covers the "interactive property explorer" redesign: unit occupancy must be computed from
 * real contract/reservation data (not guessed from the raw status column or from history),
 * the same computed status must drive the card, the status filter and the summary counts
 * alike, search must tolerate Arabic-Indic digits, and none of it should leak price or tenant
 * data to a restricted employee or let them reach a property outside their grant.
 */
final class PropertyExplorerUxTest extends TestCase
{
    use RefreshDatabase;

    private function company(string $slug = 'demo'): Company
    {
        return Company::create(['name' => $slug, 'slug' => $slug, 'country_code' => 'SA', 'currency' => 'SAR', 'is_active' => true]);
    }

    private function login(Company $company, string $role = 'company_admin'): User
    {
        $user = User::create(['company_id' => $company->id, 'name' => 'Operator', 'email' => uniqid().'@example.test',
            'password' => 'DemoStrongPassword!', 'role' => $role, 'is_active' => true]);
        $this->actingAs($user);
        return $user;
    }

    private function property(Company $company, string $name): Property
    {
        return Property::create(['company_id' => $company->id, 'name' => $name, 'status' => 'active', 'category' => 'mixed', 'country_code' => 'SA', 'city' => 'الرياض']);
    }

    private function unit(Property $property, string $code, string $status = 'available', ?int $floor = 0, string $type = 'apartment'): Unit
    {
        return Unit::create(['company_id' => $property->company_id, 'property_id' => $property->id, 'code' => $code,
            'type' => $type, 'floor' => $floor, 'status' => $status, 'monthly_rent' => 2500]);
    }

    private function tenant(Company $company, string $name): Contact
    {
        return Contact::create(['company_id' => $company->id, 'name' => $name, 'type' => 'tenant']);
    }

    private function lease(Unit $unit, Contact $tenant, string $starts, string $ends, string $status = 'active'): Lease
    {
        return Lease::create(['company_id' => $unit->company_id, 'unit_id' => $unit->id, 'contact_id' => $tenant->id,
            'contract_number' => 'L-'.uniqid(), 'starts_on' => $starts, 'ends_on' => $ends, 'billing_cycle' => 'monthly', 'rent_amount' => 2500, 'status' => $status]);
    }

    // A unit's bare code (e.g. "A1") is short enough to coincidentally collide with random
    // page noise (the CSRF meta tag, an asset version query string, a pagination number) —
    // which is exactly what made this suite flaky before this helper existed. "الوحدة A1"
    // only ever appears on that unit's own card (its data-modal-title / aria-label), so
    // asserting on it is both more precise and immune to that coincidence.
    private function marker(string $code): string
    {
        return 'الوحدة '.$code;
    }

    public function test_available_unit_is_consistent_across_card_filter_and_counts(): void
    {
        $this->travelTo(now()->setDate(2026, 11, 15));
        $company = $this->company();
        $this->login($company);
        $property = $this->property($company, 'برج الواحة');
        $unit = $this->unit($property, 'A1', 'available');

        $this->assertSame('available', $unit->fresh()->occupancy_status);

        $page = $this->get('/property-explorer/'.$property->id);
        $page->assertOk()->assertSee($this->marker('A1'))->assertSee('متاحة');

        $this->get('/property-explorer/'.$property->id.'?status=available')->assertOk()->assertSee($this->marker('A1'));
        $this->get('/property-explorer/'.$property->id.'?status=occupied')->assertOk()->assertDontSee($this->marker('A1'));
        $this->travelBack();
    }

    public function test_expired_and_future_leases_are_not_treated_as_current_occupancy(): void
    {
        $this->travelTo(now()->setDate(2026, 11, 15));
        $company = $this->company();
        $this->login($company);
        $property = $this->property($company, 'برج الأمل');
        $expiredUnit = $this->unit($property, 'B1', 'occupied'); // raw flag never flipped back
        $futureUnit = $this->unit($property, 'B2', 'occupied');
        $tenant = $this->tenant($company, 'مستأجر سابق');

        $this->lease($expiredUnit, $tenant, '2026-09-01', '2026-10-31', 'active'); // ended two weeks ago
        $this->lease($futureUnit, $tenant, '2026-12-01', '2026-12-31', 'active'); // starts next month

        $this->assertSame('available', $expiredUnit->fresh()->occupancy_status);
        $this->assertSame('available', $futureUnit->fresh()->occupancy_status);

        $response = $this->get('/property-explorer/'.$property->id.'?status=available');
        $response->assertOk()->assertSee($this->marker('B1'))->assertSee($this->marker('B2'));
        $this->get('/property-explorer/'.$property->id.'?status=occupied')->assertOk()
            ->assertDontSee($this->marker('B1'))->assertDontSee($this->marker('B2'));
        $this->travelBack();
    }

    public function test_current_active_lease_shows_as_occupied(): void
    {
        $this->travelTo(now()->setDate(2026, 11, 15));
        $company = $this->company();
        $this->login($company);
        $property = $this->property($company, 'برج النجم');
        $unit = $this->unit($property, 'C1', 'occupied');
        $tenant = $this->tenant($company, 'مستأجر حالي');
        $this->lease($unit, $tenant, '2026-11-01', '2026-11-30', 'active');

        $this->assertSame('occupied', $unit->fresh()->occupancy_status);
        $this->get('/property-explorer/'.$property->id.'?status=occupied')->assertOk()->assertSee($this->marker('C1'));
        $this->get('/property-explorer/'.$property->id.'?status=available')->assertOk()->assertDontSee($this->marker('C1'));
        $this->travelBack();
    }

    public function test_reservation_without_lease_shows_as_reserved_not_available(): void
    {
        $this->travelTo(now()->setDate(2026, 11, 15));
        $company = $this->company();
        $this->login($company);
        $property = $this->property($company, 'برج الساحل');
        $unit = $this->unit($property, 'D1', 'available');
        $tenant = $this->tenant($company, 'حاجز');
        Reservation::create(['company_id' => $company->id, 'unit_id' => $unit->id, 'contact_id' => $tenant->id,
            'reference' => 'R-1', 'check_in' => '2026-11-10', 'check_out' => '2026-11-20', 'total_price' => 500, 'status' => 'confirmed']);

        $this->assertSame('reserved', $unit->fresh()->occupancy_status);
        $this->get('/property-explorer/'.$property->id.'?status=reserved')->assertOk()->assertSee($this->marker('D1'))->assertSee('محجوزة');
        $this->get('/property-explorer/'.$property->id.'?status=available')->assertOk()->assertDontSee($this->marker('D1'));
        $this->get('/property-explorer/'.$property->id.'?status=occupied')->assertOk()->assertDontSee($this->marker('D1'));
        $this->travelBack();
    }

    public function test_maintenance_and_inactive_units_are_never_shown_as_available(): void
    {
        $company = $this->company();
        $this->login($company);
        $property = $this->property($company, 'برج الصيانة');
        $maintenance = $this->unit($property, 'E1', 'maintenance');
        $inactive = $this->unit($property, 'E2', 'inactive');

        $this->assertSame('maintenance', $maintenance->fresh()->occupancy_status);
        $this->assertSame('inactive', $inactive->fresh()->occupancy_status);
        $this->get('/property-explorer/'.$property->id.'?status=available')->assertOk()
            ->assertDontSee($this->marker('E1'))->assertDontSee($this->marker('E2'));
        $this->get('/property-explorer/'.$property->id.'?status=maintenance')->assertOk()->assertSee($this->marker('E1'));
        $this->get('/property-explorer/'.$property->id.'?status=inactive')->assertOk()->assertSee($this->marker('E2'));
    }

    public function test_unrecognized_raw_status_is_shown_as_undetermined_instead_of_guessed_available(): void
    {
        $company = $this->company();
        $this->login($company);
        $property = $this->property($company, 'برج قديم');
        $unit = $this->unit($property, 'F1', 'available');
        // Simulate a corrupted / pre-migration / historical-import value that bypasses the
        // application's own select-field validation entirely.
        DB::table('units')->where('id', $unit->id)->update(['status' => 'legacy_unmapped']);

        $this->assertSame('unknown', $unit->fresh()->occupancy_status);
        $this->get('/property-explorer/'.$property->id.'?status=available')->assertOk()->assertDontSee($this->marker('F1'));
        $this->get('/property-explorer/'.$property->id.'?status=unknown')->assertOk()->assertSee($this->marker('F1'))->assertSee('غير محددة');
    }

    public function test_combined_floor_type_and_status_filters_match_summary_counts(): void
    {
        $company = $this->company();
        $this->login($company);
        $property = $this->property($company, 'برج متعدد');
        $this->unit($property, 'G1', 'available', 1, 'apartment');
        $this->unit($property, 'G2', 'available', 1, 'shop');
        $this->unit($property, 'G3', 'available', 2, 'apartment');
        $this->unit($property, 'G4', 'maintenance', 2, 'apartment');

        // Unfiltered: all four show up regardless of any filter, and the summary's grand
        // total always reflects the property, never the current result set.
        $unfiltered = $this->get('/property-explorer/'.$property->id);
        $unfiltered->assertOk()
            ->assertSee($this->marker('G1'))->assertSee($this->marker('G2'))
            ->assertSee($this->marker('G3'))->assertSee($this->marker('G4'));

        // Floor 1 + apartment + available -> only G1.
        $filtered = $this->get('/property-explorer/'.$property->id.'?floor=1&type=apartment&status=available');
        $filtered->assertOk()->assertSee($this->marker('G1'))
            ->assertDontSee($this->marker('G2'))->assertDontSee($this->marker('G3'))->assertDontSee($this->marker('G4'));

        // Floor 2 + apartment, no status filter -> G3 (available) and G4 (maintenance), never guessed as available.
        $floor2 = $this->get('/property-explorer/'.$property->id.'?floor=2&type=apartment');
        $floor2->assertOk()->assertSee($this->marker('G3'))->assertSee($this->marker('G4'))
            ->assertDontSee($this->marker('G1'))->assertDontSee($this->marker('G2'));
    }

    public function test_search_matches_code_with_arabic_indic_digits(): void
    {
        $company = $this->company();
        $this->login($company);
        $property = $this->property($company, 'برج الأرقام');
        $this->unit($property, 'A101', 'available');
        $this->unit($property, 'B202', 'available');

        $this->get('/property-explorer/'.$property->id.'?q=A101')->assertOk()
            ->assertSee($this->marker('A101'))->assertDontSee($this->marker('B202'));
        // Same search typed with Arabic-Indic digits must still find it.
        $this->get('/property-explorer/'.$property->id.'?'.http_build_query(['q' => 'A١٠١']))
            ->assertOk()->assertSee($this->marker('A101'))->assertDontSee($this->marker('B202'));
    }

    public function test_closing_a_lease_flips_the_unit_back_to_available_on_reload(): void
    {
        $this->travelTo(now()->setDate(2026, 11, 15));
        $company = $this->company();
        $this->login($company);
        $property = $this->property($company, 'برج التحديث');
        $unit = $this->unit($property, 'H1', 'occupied');
        $tenant = $this->tenant($company, 'مستأجر');
        $lease = $this->lease($unit, $tenant, '2026-11-01', '2026-11-30', 'active');

        $this->get('/property-explorer/'.$property->id.'?status=occupied')->assertOk()->assertSee($this->marker('H1'));

        $this->patch('/leases/'.$lease->id.'/close')->assertRedirect();

        $this->assertSame('available', $unit->fresh()->occupancy_status);
        $this->get('/property-explorer/'.$property->id.'?status=available')->assertOk()->assertSee($this->marker('H1'));
        $this->get('/property-explorer/'.$property->id.'?status=occupied')->assertOk()->assertDontSee($this->marker('H1'));
        $this->travelBack();
    }

    public function test_restricted_employee_sees_status_but_cannot_reach_an_ungranted_property(): void
    {
        $company = $this->company();
        $this->login($company, 'company_admin');
        $granted = $this->property($company, 'عقار مصرّح به');
        $other = $this->property($company, 'عقار آخر');
        $occupiedUnit = $this->unit($granted, 'I1', 'occupied');
        $tenant = $this->tenant($company, 'مستأجر');
        $this->lease($occupiedUnit, $tenant, now()->toDateString(), now()->addMonth()->toDateString());
        $this->unit($other, 'J1', 'available');

        $employee = User::create(['company_id' => $company->id, 'name' => 'موظف محدود', 'email' => uniqid().'@example.test',
            'password' => 'DemoStrongPassword!', 'role' => 'agent', 'is_active' => true, 'scope_properties' => true]);
        PropertyAccessGrant::create(['company_id' => $company->id, 'user_id' => $employee->id, 'property_id' => $granted->id,
            'can_view_property' => true, 'can_view_sale_price' => false, 'can_view_rent_price' => false, 'can_edit_prices' => false]);

        $this->actingAs($employee);
        $this->get('/property-explorer/'.$granted->id)->assertOk()
            ->assertSee($this->marker('I1'))->assertSee('مؤجرة')
            ->assertDontSee('2,500')->assertDontSee('2500.000');
        $this->get('/property-explorer/'.$other->id)->assertNotFound();
    }

    public function test_explorer_grid_renders_every_occupancy_state_without_error(): void
    {
        $company = $this->company();
        $this->login($company);
        $property = $this->property($company, 'برج شامل');
        $this->unit($property, 'K1', 'available');
        $occupied = $this->unit($property, 'K2', 'occupied');
        $this->lease($occupied, $this->tenant($company, 'ت'), now()->toDateString(), now()->addMonth()->toDateString());
        $reserved = $this->unit($property, 'K3', 'available');
        Reservation::create(['company_id' => $company->id, 'unit_id' => $reserved->id, 'contact_id' => $this->tenant($company, 'ح')->id,
            'reference' => 'R-K3', 'check_in' => now()->toDateString(), 'check_out' => now()->addWeek()->toDateString(), 'total_price' => 300, 'status' => 'confirmed']);
        $this->unit($property, 'K4', 'maintenance');
        $this->unit($property, 'K5', 'inactive');
        DB::table('units')->insert(['company_id' => $company->id, 'property_id' => $property->id, 'code' => 'K6',
            'type' => 'apartment', 'status' => 'odd_value', 'monthly_rent' => 0, 'created_at' => now(), 'updated_at' => now()]);

        $response = $this->get('/property-explorer/'.$property->id);
        $response->assertOk();
        foreach (['متاحة', 'مؤجرة', 'محجوزة', 'صيانة', 'غير نشطة', 'غير محددة'] as $label) {
            $response->assertSee($label);
        }
        foreach (['K1', 'K2', 'K3', 'K4', 'K5', 'K6'] as $code) {
            $response->assertSee($this->marker($code));
        }
    }
}
