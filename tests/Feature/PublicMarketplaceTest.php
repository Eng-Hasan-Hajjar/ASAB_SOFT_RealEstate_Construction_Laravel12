<?php
namespace Tests\Feature;
use App\Models\{Company,User,Property,Unit,PropertyInquiry,Lead};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
class PublicMarketplaceTest extends TestCase {
 use RefreshDatabase;
 private function company(string $slug):Company{return Company::create(['name'=>'Company '.$slug,'slug'=>$slug,'country_code'=>'SA','currency'=>'SAR','is_active'=>true]);}
 private function property(Company $company,bool $published=true):Property{
  $p=Property::create(['company_id'=>$company->id,'name'=>'Property '.$company->slug,'country_code'=>'SA','city'=>'Dammam','status'=>'active','is_published'=>$published,'published_at'=>now()]);
  Unit::create(['company_id'=>$company->id,'property_id'=>$p->id,'code'=>'A-10','status'=>'available','is_listed'=>true,'monthly_rent'=>3000,'type'=>'apartment','listing_type'=>'rent']);
  return $p;
 }
 private function user(Company $company,string $role='company_admin'):User{return User::create(['company_id'=>$company->id,'name'=>'Manager','email'=>uniqid('manager'). '@example.test','password'=>'LocalSecurePassword2026','is_active'=>true,'role'=>$role]);}
 public function test_public_home_loads_when_database_has_no_listings():void {$this->get('/')->assertOk()->assertSee('عصب سوفت');}
 public function test_published_property_shows_but_private_is_hidden():void {
  $a=$this->company('a');$p=$this->property($a,true);$other=$this->property($a,false);$other->update(['name'=>'Secret Unpublished Tower']);
  $this->get('/properties')->assertOk()->assertSee($p->name)->assertDontSee('Secret Unpublished Tower');
  $this->get('/properties/'.$p->id)->assertOk()->assertSee('A-10');
  $this->get('/properties/'.$other->id)->assertNotFound();
 }
 public function test_inquiry_creates_scoped_inquiry_and_crm_lead():void {
  $a=$this->company('inquiry');$p=$this->property($a);
  $unit=$p->units()->first();
  $this->post('/properties/'.$p->id.'/inquire',['name'=>'عميل اختباري','phone'=>'+966551234567','email'=>'visitor@example.test','unit_id'=>$unit->id,'message'=>'هل الوحدة متاحة؟','privacy_accepted'=>'1'])->assertRedirect();
  $this->assertDatabaseHas('property_inquiries',['company_id'=>$a->id,'property_id'=>$p->id,'unit_id'=>$unit->id,'phone'=>'+966551234567']);
  $this->assertDatabaseHas('leads',['company_id'=>$a->id,'email'=>'visitor@example.test']);
 }
 public function test_cannot_inquire_about_another_propertys_unit():void {
  $a=$this->company('x');$b=$this->company('y');$p=$this->property($a);$foreign=$this->property($b);
  $this->post('/properties/'.$p->id.'/inquire',['name'=>'Test','phone'=>'+966500000000','unit_id'=>$foreign->units()->first()->id,'privacy_accepted'=>'1'])->assertSessionHasErrors('unit_id');
  $this->assertDatabaseCount('property_inquiries',0);
 }
 public function test_company_cannot_manage_other_companys_listing():void {
  $a=$this->company('one');$b=$this->company('two');$other=$this->property($b);$actor=$this->user($a);
  $this->actingAs($actor)->get('/listings/'.$other->id.'/edit')->assertNotFound();
  $this->actingAs($actor)->post('/listings/'.$other->id.'/publish')->assertNotFound();
 }
 public function test_platform_owner_can_enter_and_leave_a_workspace():void {
  $company=$this->company('tenant1');$owner=User::create(['name'=>'Platform Owner','email'=>'platform@example.test','password'=>'LocalSecurePassword2026','role'=>'super_admin','is_active'=>true]);
  $this->actingAs($owner)->get('/dashboard')->assertForbidden();
  $this->actingAs($owner)->post('/platform/companies/'.$company->id.'/enter')->assertRedirect('/dashboard');
  $this->actingAs($owner)->get('/dashboard')->assertOk();
  $this->actingAs($owner)->get('/manage/properties/create')->assertOk();
  $this->actingAs($owner)->post('/platform/leave')->assertRedirect('/platform');
  $this->actingAs($owner)->get('/dashboard')->assertForbidden();
 }
 public function test_unavailable_unit_not_public_even_when_listed():void {
  $company=$this->company('occupied');$p=$this->property($company);$p->units()->update(['status'=>'occupied']);
  $this->get('/properties/'.$p->id)->assertNotFound();
  $this->get('/properties')->assertDontSee($p->name);
 }
}
