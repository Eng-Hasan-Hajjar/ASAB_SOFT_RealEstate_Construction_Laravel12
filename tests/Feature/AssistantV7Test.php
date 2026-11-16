<?php
namespace Tests\Feature;

use App\Models\{Company,Contact,Invoice,Payment,User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class AssistantV7Test extends TestCase
{
    use RefreshDatabase;

    private function workspace(string $slug,string $role):Company
    {
        $c=Company::create(['name'=>'مساحة '.$slug,'slug'=>$slug,'country_code'=>'SA','currency'=>'SAR','is_active'=>true]);
        $this->actingAs(User::create(['company_id'=>$c->id,'name'=>'حسن التجريبي','email'=>$slug.'@example.test',
            'password'=>'OnlyForTests.99','role'=>$role,'is_active'=>true]));
        return $c;
    }

    public function test_account_and_personal_finance_questions_match_the_reported_examples():void
    {
        $this->workspace('one','company_admin');
        foreach(['ما هو اسمي؟','ماهو اسمي هنا في المنصة','شو اسمي المسجل؟'] as $question){
            $this->postJson('/assistant/ask',compact('question'))->assertOk()->assertJsonPath('intent','account_name')
                ->assertJsonPath('answer','اسم حسابك المسجّل: حسن التجريبي.');
        }
        $this->postJson('/assistant/ask',['question'=>'كيف أعرف أنا كمستأجر أنه لا يوجد علي دفعات من خلال المنصة'])
            ->assertOk()->assertJsonPath('intent','personal_balance');
        $this->postJson('/assistant/ask',['question'=>'دفعاتي'])->assertOk()->assertJsonPath('intent','personal_balance');
        $this->postJson('/assistant/ask',['question'=>'كيف أضيف صوراً للعقار؟'])->assertOk()->assertJsonPath('intent','guide_gallery');
        $this->postJson('/assistant/ask',['question'=>'ما عقود الإيجار التي تنتهي خلال ١٥ يوماً؟'])->assertOk()
            ->assertJsonPath('intent','expiring_leases')->assertJsonPath('answer',fn($v)=>str_contains($v,'15 يوماً'));
        $this->get('/assistant')->assertOk()->assertSee('themeToggle')->assertSee('طي أو فتح القائمة')->assertDontSee('Excel');
    }

    public function test_operational_navigation_and_assistant_intents_are_available():void
    {
        $this->workspace('operations','company_admin');
        foreach(['كم عقار عندي؟'=>'portfolio','كم طلب صيانة مفتوح؟'=>'maintenance','العقود المنتهية'=>'ended_leases',
            'كيف أسجل دفعة؟'=>'guide_payments','كيف أبحث بالجنسية؟'=>'guide_filters','مرحبا، ما اسمي؟'=>'account_name'] as $question=>$intent){
            $this->postJson('/assistant/ask',compact('question'))->assertOk()->assertJsonPath('intent',$intent);
        }
        foreach(['/dashboard','/asab/history/rentals','/asab/history/finance','/manage/units'] as $url)
            $this->get($url)->assertOk()->assertDontSee('>Excel<',false)->assertSee('المظهر',false);
    }

    public function test_financial_lookup_uses_company_tenant_identity_and_role():void
    {
        $other=$this->workspace('other','company_admin');
        $outsider=Contact::create(['company_id'=>$other->id,'name'=>'اسم في شركة أخرى','type'=>'tenant']);
        $c=$this->workspace('pilot','accountant');
        $tenant=Contact::create(['company_id'=>$c->id,'name'=>'أحمد الاختبار','type'=>'tenant']);
        $bill=Invoice::create(['company_id'=>$c->id,'contact_id'=>$tenant->id,'number'=>'LOCAL-42','issued_on'=>today()->toDateString(),'due_on'=>today()->toDateString(),'amount'=>120,'status'=>'partially_paid']);
        Payment::create(['company_id'=>$c->id,'invoice_id'=>$bill->id,'amount'=>42,'paid_on'=>today()->toDateString(),'method'=>'cash']);
        $this->postJson('/assistant/ask',['question'=>'كم إجمالي الدفعات هذا الشهر؟'])->assertOk()->assertJsonPath('intent','posted_payments')
            ->assertJsonPath('answer',fn($v)=>str_contains($v,'42.00') && str_contains($v,'LOCAL-42'));
        $this->postJson('/assistant/ask',['question'=>'مستحقات المستأجر: احمد الاختبار'])->assertOk()->assertJsonPath('intent','unpaid_invoices')
            ->assertJsonPath('answer',fn($v)=>str_contains($v,'78.00'));
        $this->postJson('/assistant/ask',['question'=>'مستحقات مستأجر رقم '.$outsider->id])->assertOk()->assertJsonPath('intent','unknown_tenant');
        $this->workspace('readonly','viewer');
        $this->postJson('/assistant/ask',['question'=>'كم إجمالي الدفعات هذا الشهر؟'])->assertOk()->assertJsonPath('intent','finance_access');
    }
}
