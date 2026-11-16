<?php
namespace Tests\Feature;

use App\Models\{AsabFinancialHistory,Company,Contact,FinancialAccount,Invoice,Lease,Property,Unit,User,WorkspaceImage};
use App\Services\{AsabFinancePosting,AsabReceiptMatcher,WorkspaceGallery};
use App\Support\ExcelMoney;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\{Crypt,DB};
use Tests\TestCase;

final class WorkspaceV6Test extends TestCase
{
    use RefreshDatabase;

    private function company(string $slug='demo'): Company
    {
        return Company::create(['name'=>$slug,'slug'=>$slug,'country_code'=>'SA','currency'=>'SAR','is_active'=>true]);
    }

    private function login(Company $company,string $role='company_admin'):void
    {
        $this->actingAs(User::create(['company_id'=>$company->id,'name'=>'Operator','email'=>uniqid().'@example.test',
            'password'=>'DemoStrongPassword!','role'=>$role,'is_active'=>true]));
    }

    private function property(Company $company,string $name):Property
    {
        return Property::create(['company_id'=>$company->id,'name'=>$name,'status'=>'active','category'=>'mixed','country_code'=>'SA','city'=>'الخبر']);
    }

    private function unit(Property $property,string $code,string $status='available'):Unit
    {
        return Unit::create(['company_id'=>$property->company_id,'property_id'=>$property->id,'code'=>$code,
            'type'=>'apartment','floor'=>0,'status'=>$status,'monthly_rent'=>2500]);
    }

    private function tenant(Company $company,string $name,string $nationality):Contact
    {
        return Contact::create(['company_id'=>$company->id,'name'=>$name,'type'=>'tenant','nationality'=>$nationality]);
    }

    private function lease(Unit $unit,Contact $tenant,string $starts,string $ends,string $status='active'):Lease
    {
        return Lease::create(['company_id'=>$unit->company_id,'unit_id'=>$unit->id,'contact_id'=>$tenant->id,
            'contract_number'=>'L-'.uniqid(),'starts_on'=>$starts,'ends_on'=>$ends,'billing_cycle'=>'monthly','rent_amount'=>2500,'status'=>$status]);
    }

    public function test_explorer_filters_and_modals_use_calendar_and_company():void
    {
        $this->travelTo(now()->setDate(2026,11,15));
        $company=$this->company();$this->login($company);
        $property=$this->property($company,'مبنى النجاح');
        $outsider=$this->property($this->company('other'),'خاص بشركة أخرى');
        $unit=$this->unit($property,'A101','occupied');
        $former=$this->tenant($company,'سالم القديم','أردني');
        $current=$this->tenant($company,'مستأجر حالي','سوري');
        $this->lease($unit,$former,'2026-10-01','2026-10-31');
        $this->assertSame('available',$unit->fresh()->occupancy_status);
        $this->get('/manage/properties?occupancy=all_available&asset_sort=units_count')->assertOk()->assertSee('مبنى النجاح');
        $this->get('/manage/units?f[status]=available&asset_floor=0')->assertOk()->assertSee('A101');
        $this->lease($unit,$current,'2026-11-01','2026-11-30');
        $this->get('/property-explorer')->assertOk()->assertSee('مبنى النجاح')->assertDontSee('خاص بشركة أخرى');
        $this->get('/property-explorer/'.$outsider->id)->assertNotFound();
        $this->get('/property-explorer/'.$property->id.'?status=occupied')->assertOk()->assertSee('A101')->assertSee('الطابق الأرضي');
        $this->get('/manage/contacts?asset_nat=سوري&contact_lease=active')->assertOk()->assertSee('مستأجر حالي')->assertDontSee('سالم القديم');
        $this->get('/manage/contacts?contact_lease=former')->assertOk()->assertSee('سالم القديم')->assertDontSee('مستأجر حالي');
        $this->get('/manage/units/'.$unit->id.'/preview',['X-Requested-With'=>'XMLHttpRequest'])->assertOk()->assertSee('مستأجر حالي')->assertSee('سالم القديم');
        $this->get('/manage/units/'.$unit->id)->assertOk()->assertSee('معرض الصور');
        $this->travelTo(now()->setDate(2026,12,1));
        $this->assertSame('available',$unit->fresh()->occupancy_status);
        $this->get('/manage/contacts/'.$current->id)->assertOk()->assertSee('غير مرتبط بعقد إيجار حالي');
        $this->travelBack();
    }

    public function test_spaces_and_gallery_are_optional_private_and_editable():void
    {
        $company=$this->company();$this->login($company);$property=$this->property($company,'Portfolio');
        $payload=['property_id'=>$property->id,'code'=>'Shop A','type'=>'shop','status'=>'available','monthly_rent'=>450,'spaces_present'=>1];
        $this->get('/manage/units/create?property_id='.$property->id)->assertOk()->assertSee('الصور والمساحات');
        $this->post('/manage/units',$payload+['spaces'=>[
            ['kind'=>'shop_floor','name'=>'صالة العرض','photos'=>[UploadedFile::fake()->image('cover.png',20,20)]],
            ['kind'=>'bathroom','name'=>'حمام واحد'],
        ]])->assertSessionHasNoErrors()->assertRedirect('/manage/units');
        $unit=Unit::where('code','Shop A')->firstOrFail();
        $this->assertSame(0,(int)$unit->rooms);$this->assertSame(1,(int)$unit->bathrooms);
        $pic=WorkspaceImage::where('unit_id',$unit->id)->firstOrFail();
        try {
            $this->get('/manage/units/'.$unit->id)->assertOk()->assertSee('صالة العرض');
            $this->get('/workspace-gallery/'.$pic->id)->assertOk();
            $this->login($this->company('intruder'));
            $this->get('/workspace-gallery/'.$pic->id)->assertNotFound();
            $this->login($company);
            $this->get('/manage/units/'.$unit->id.'/edit')->assertOk()->assertSee('صالة العرض');
            $this->put('/manage/units/'.$unit->id,$payload)->assertSessionHasNoErrors()->assertRedirect('/manage/units');
            $this->assertDatabaseMissing('workspace_images',['id'=>$pic->id]);
            $this->assertFalse(WorkspaceGallery::disk()->exists($pic->path));
        }finally {app(WorkspaceGallery::class)->cleanup([$pic->path]);}
    }

    public function test_invoice_receipt_review_creates_one_payment_and_posting():void
    {
        $company=$this->company();$property=$this->property($company,'Test');$unit=$this->unit($property,'A');
        $tenant=$this->tenant($company,'Sample payer','SA');
        $lease=$this->lease($unit,$tenant,'2026-10-01','2026-11-30');
        $invoice=Invoice::create(['company_id'=>$company->id,'lease_id'=>$lease->id,'contact_id'=>$tenant->id,
            'number'=>'I-100','issued_on'=>'2026-11-01','due_on'=>'2026-11-01','period_start'=>'2026-11-01','period_end'=>'2026-11-30',
            'amount'=>1000,'status'=>'unpaid']);
        $account=FinancialAccount::create(['company_id'=>$company->id,'name'=>'Bank','type'=>'bank','currency'=>'SAR','is_active'=>true]);
        $receipt=AsabFinancialHistory::create(['company_id'=>$company->id,'property_id'=>$property->id,'unit_id'=>$unit->id,'source_key'=>str_repeat('a',64),
            'source_file'=>'test','source_sheet'=>'bank','source_row'=>15,'source_column'=>'F','event_on'=>'2026-11-05','flow'=>'in','channel'=>'bank',
            'party_name'=>'Sample payer','source_amount'=>750,'encrypted_source'=>Crypt::encryptString('{"H":"09:00"}')]);
        $this->assertSame(1,app(AsabReceiptMatcher::class)->execute($company,true)['ready_for_review']);
        $this->assertDatabaseCount('payments',0);
        $this->login($company,'viewer');
        $this->get('/asab/receipts/'.$receipt->id)->assertForbidden();
        $this->login($company,'accountant');
        $this->get('/asab/history/finance?flow=in')->assertOk()->assertSee('مطابقة مقترحة');
        $this->get('/asab/receipts/'.$receipt->id)->assertOk()->assertSee('I-100');
        $uri='/asab/receipts/'.$receipt->id;
        $data=['invoice_id'=>$invoice->id,'financial_account_id'=>$account->id,'posted_on'=>'2026-11-05','evidence_reference'=>'REF-001','confirmed'=>'1'];
        $this->post($uri,array_replace($data,['posted_on'=>'2026-11-06']))->assertSessionHasErrors('receipt');
        $this->post($uri,$data)->assertSessionHasNoErrors()->assertRedirect($uri);
        $this->post($uri,$data)->assertRedirect($uri);
        $this->assertSame('partially_paid',$invoice->fresh()->status);
        $this->assertSame(250.0,$invoice->fresh()->balance);
        $this->assertDatabaseCount('payments',1);
        $this->assertDatabaseCount('ledger_entries',1);
        $this->assertDatabaseCount('asab_verified_finance_postings',1);
    }

    public function test_excel_numeric_tail_is_not_arbitrary_rounding():void
    {
        $this->assertSame('2484.680',ExcelMoney::parse('2484.6799999999998'));
        $this->assertNull(ExcelMoney::parse('2484.6799'));
        $this->assertSame('1200.500',ExcelMoney::parse('1,200.5'));
        $this->assertNull(ExcelMoney::parse('#VALUE!'));
    }
}
