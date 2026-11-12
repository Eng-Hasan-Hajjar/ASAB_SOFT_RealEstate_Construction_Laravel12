<?php
namespace Tests\Feature;
use App\Models\{Company,User,Property,Unit,Contact,Lease,Invoice};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
class PlatformSecurityTest extends TestCase {
 use RefreshDatabase;
 private function company(string $slug):Company{return Company::create(['name'=>$slug,'slug'=>$slug,'country_code'=>'SA','currency'=>'SAR','is_active'=>true]);}
 private function staff(Company $c,string $role='company_admin'):User{return User::create(['company_id'=>$c->id,'name'=>'Test User','email'=>uniqid('user'). '@example.test','password'=>'S3curePassword!2026','role'=>$role,'is_active'=>true]);}
 public function test_guests_are_redirected_to_login():void {$this->get('/dashboard')->assertRedirect('/login');}
 public function test_company_cannot_read_another_companys_property():void {
  $a=$this->company('alpha');$b=$this->company('beta');$staff=$this->staff($a);
  Property::create(['company_id'=>$b->id,'name'=>'Secret building','country_code'=>'SA','city'=>'Riyadh','category'=>'residential','status'=>'active']);
  $this->actingAs($staff)->get('/manage/properties')->assertOk()->assertDontSee('Secret building');
 }
 public function test_company_cannot_reference_another_tenants_property():void {
  $a=$this->company('alpha');$b=$this->company('beta');$staff=$this->staff($a);
  $prop=Property::create(['company_id'=>$b->id,'name'=>'Building','country_code'=>'SA','city'=>'Manama','category'=>'residential','status'=>'active']);
  $this->actingAs($staff)->post('/manage/units',['property_id'=>$prop->id,'code'=>'A1','type'=>'apartment','monthly_rent'=>100,'status'=>'available'])->assertSessionHasErrors('property_id');
 }
 public function test_only_platform_owner_can_open_platform():void {
  $a=$this->company('alpha');$staff=$this->staff($a);
  $this->actingAs($staff)->get('/platform')->assertForbidden();
  $owner=User::create(['name'=>'Owner','email'=>'owner@example.test','password'=>'S3curePassword!2026','role'=>'super_admin','is_active'=>true]);
  $this->actingAs($owner)->get('/platform')->assertOk();
 }
 public function test_viewer_cannot_change_operational_records():void {
  $a=$this->company('alpha');$viewer=$this->staff($a,'viewer');
  $this->actingAs($viewer)->post('/manage/contacts',['name'=>'Attempt','type'=>'tenant'])->assertForbidden();
 }
 public function test_overlapping_active_lease_is_rejected():void {
  $a=$this->company('alpha');$staff=$this->staff($a);
  $prop=Property::create(['company_id'=>$a->id,'name'=>'A','country_code'=>'SA','city'=>'Riyadh','category'=>'residential','status'=>'active']);
  $unit=Unit::create(['company_id'=>$a->id,'property_id'=>$prop->id,'code'=>'101','monthly_rent'=>3000,'status'=>'occupied','type'=>'apartment']);
  $tenant=Contact::create(['company_id'=>$a->id,'name'=>'Tenant','type'=>'tenant']);
  Lease::create(['company_id'=>$a->id,'unit_id'=>$unit->id,'contact_id'=>$tenant->id,'contract_number'=>'L-A','starts_on'=>'2026-01-01','ends_on'=>'2026-12-31','rent_amount'=>3000,'deposit'=>0,'status'=>'active','billing_cycle'=>'monthly']);
  $this->actingAs($staff)->post('/leases',['unit_id'=>$unit->id,'contact_id'=>$tenant->id,'contract_number'=>'L-B','starts_on'=>'2026-10-01','ends_on'=>'2027-03-01','rent_amount'=>3000,'deposit'=>0,'status'=>'active','billing_cycle'=>'monthly'])->assertSessionHasErrors('unit_id');
  $this->assertDatabaseMissing('leases',['contract_number'=>'L-B']);
 }
 public function test_payment_cannot_exceed_remaining_balance():void {
  $a=$this->company('alpha');$staff=$this->staff($a);$tenant=Contact::create(['company_id'=>$a->id,'name'=>'Tenant','type'=>'tenant']);
  $invoice=Invoice::create(['company_id'=>$a->id,'contact_id'=>$tenant->id,'number'=>'INV-001','issued_on'=>'2026-11-01','due_on'=>'2026-12-01','amount'=>100,'status'=>'unpaid']);
  $this->actingAs($staff)->post('/invoices/'.$invoice->id.'/payments',['amount'=>200,'paid_on'=>'2026-11-02','method'=>'cash'])->assertSessionHasErrors('amount');
  $this->assertDatabaseCount('payments',0);
 }
}
