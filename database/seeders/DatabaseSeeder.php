<?php
namespace Database\Seeders;
use App\Models\{Company,User,Property,Unit,Contact,Lease,Invoice,Payment,FinancialAccount,LedgerEntry,MaintenanceTicket,Contractor,ConstructionProject,ProjectTask,ProjectExpense,Lead,KnowledgeEntry};
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use RuntimeException;
class DatabaseSeeder extends Seeder {
 public function run():void {
  $email=env('PLATFORM_OWNER_EMAIL');$password=env('PLATFORM_OWNER_PASSWORD');$name=env('PLATFORM_OWNER_NAME','Platform Owner');
  if(!filter_var($email,FILTER_VALIDATE_EMAIL)||strlen((string)$password)<12) throw new RuntimeException('Set PLATFORM_OWNER_EMAIL and PLATFORM_OWNER_PASSWORD (12+ characters) in .env before seeding. No default admin credentials are supplied.');
  DB::transaction(function()use($email,$password,$name){
   $owner=User::firstOrNew(['email'=>$email]);
   if($owner->exists && $owner->role!=='super_admin')throw new RuntimeException('The selected platform email belongs to a different role.');
   $owner->fill(['name'=>$name,'company_id'=>null,'role'=>'super_admin','is_active'=>true]);
   // Never reset the existing administrator's password silently when the seeder reruns.
   if(!$owner->exists)$owner->password=$password;
   $owner->save();
  });
  if(filter_var(env('DEMO_DATA',false),FILTER_VALIDATE_BOOLEAN)){$this->demo();$this->showcaseDemo();}
 }
 private function demo():void {
  DB::transaction(function(){
   $sa=Company::firstOrCreate(['slug'=>'demo-sa'],['name'=>'شركة العقار الحديث (بيانات تجريبية)','country_code'=>'SA','currency'=>'SAR','city'=>'الدمام','is_active'=>true]);
   $bh=Company::firstOrCreate(['slug'=>'demo-bh'],['name'=>'شركة البحرين التجريبية','country_code'=>'BH','currency'=>'BHD','city'=>'المنامة','is_active'=>true]);
   // Optional demo company administrator; credentials MUST be supplied by the operator.
   $demoEmail=env('DEMO_COMPANY_EMAIL');$demoPassword=env('DEMO_COMPANY_PASSWORD');
   if($demoEmail||$demoPassword){
    if(!filter_var($demoEmail,FILTER_VALIDATE_EMAIL)||strlen((string)$demoPassword)<12)throw new RuntimeException('DEMO_COMPANY_EMAIL and DEMO_COMPANY_PASSWORD must be valid and 12+ characters when specified.');
    $existing=User::where('email',$demoEmail)->first();
    if($existing && (int)$existing->company_id!==$sa->id)throw new RuntimeException('DEMO_COMPANY_EMAIL already belongs to another account.');
    if(!$existing)User::create(['company_id'=>$sa->id,'name'=>'مدير الشركة التجريبية','email'=>$demoEmail,'password'=>$demoPassword,'role'=>'company_admin','is_active'=>true]);
   }
   foreach([$sa,$bh] as $co){
    FinancialAccount::firstOrCreate(['company_id'=>$co->id,'name'=>'الصندوق الرئيسي'],['type'=>'cash','currency'=>$co->currency,'opening_balance'=>0,'is_active'=>true]);
    FinancialAccount::firstOrCreate(['company_id'=>$co->id,'name'=>'الحساب البنكي الرئيسي'],['type'=>'bank','currency'=>$co->currency,'opening_balance'=>0,'is_active'=>true]);
   }
   // Example data are deliberately fictional, without real customer PII or reusable passwords.
   if(Property::where('company_id',$sa->id)->exists())return;
   $p=Property::create(['company_id'=>$sa->id,'name'=>'برج اللؤلؤ النموذجي','category'=>'mixed','country_code'=>'SA','city'=>'الخبر','district'=>'العليا','area'=>2500,'status'=>'active','description'=>'بيانات لغرض العرض فقط.']);
   $p2=Property::create(['company_id'=>$sa->id,'name'=>'فلل الخليج النموذجية','category'=>'residential','country_code'=>'SA','city'=>'الدمام','district'=>'الشاطئ','area'=>1800,'status'=>'active']);
   $u1=Unit::create(['company_id'=>$sa->id,'property_id'=>$p->id,'code'=>'A-101','type'=>'apartment','rooms'=>3,'bathrooms'=>2,'area'=>130,'monthly_rent'=>2900,'status'=>'occupied']);
   Unit::create(['company_id'=>$sa->id,'property_id'=>$p->id,'code'=>'A-102','type'=>'apartment','rooms'=>2,'area'=>93,'monthly_rent'=>2400,'status'=>'available']);
   Unit::create(['company_id'=>$sa->id,'property_id'=>$p->id,'code'=>'S-01','type'=>'shop','area'=>81,'monthly_rent'=>5300,'status'=>'available']);
   Unit::create(['company_id'=>$sa->id,'property_id'=>$p2->id,'code'=>'V-07','type'=>'villa','rooms'=>5,'area'=>320,'monthly_rent'=>7700,'status'=>'available']);
   $tenant=Contact::create(['company_id'=>$sa->id,'name'=>'عميل تجريبي 001','type'=>'tenant','city'=>'الخبر','notes'=>'بيانات اختبارية ولا تمثل شخصاً حقيقياً.']);
   $client=Contact::create(['company_id'=>$sa->id,'name'=>'جهة مقاولات تجريبية','type'=>'buyer','city'=>'الدمام']);
   $lease=Lease::create(['company_id'=>$sa->id,'unit_id'=>$u1->id,'contact_id'=>$tenant->id,'contract_number'=>'DEMO-L-001','starts_on'=>today()->subMonths(2),'ends_on'=>today()->addMonths(10),'rent_amount'=>2900,'deposit'=>2000,'billing_cycle'=>'monthly','status'=>'active']);
   $invoice=Invoice::create(['company_id'=>$sa->id,'lease_id'=>$lease->id,'contact_id'=>$tenant->id,'number'=>'DEMO-I-001','issued_on'=>today()->subDays(13),'due_on'=>today()->addDays(14),'amount'=>2900,'status'=>'partially_paid','description'=>'إيجار نموذجي']);
   $payment=Payment::create(['company_id'=>$sa->id,'invoice_id'=>$invoice->id,'amount'=>900,'paid_on'=>today()->subDays(7),'method'=>'transfer','reference'=>'DEMO-ONLY']);
   LedgerEntry::create(['company_id'=>$sa->id,'financial_account_id'=>FinancialAccount::where('company_id',$sa->id)->where('type','bank')->firstOrFail()->id,'property_id'=>$p->id,'unit_id'=>$u1->id,'contact_id'=>$tenant->id,'invoice_id'=>$invoice->id,'source_payment_id'=>$payment->id,'posted_on'=>today()->subDays(7),'direction'=>'in','category'=>'rent','amount'=>900,'description'=>'تحصيل تجريبي']);
   MaintenanceTicket::create(['company_id'=>$sa->id,'property_id'=>$p->id,'unit_id'=>$u1->id,'contact_id'=>$tenant->id,'title'=>'فحص تكييف الوحدة','priority'=>'high','status'=>'open','due_on'=>today()->addDays(2),'description'=>'طلب اختباري.']);
   $contractor=Contractor::create(['company_id'=>$sa->id,'name'=>'مؤسسة التنفيذ النموذجية','specialty'=>'التشطيبات']);
   $project=ConstructionProject::create(['company_id'=>$sa->id,'property_id'=>$p2->id,'contact_id'=>$client->id,'code'=>'DEMO-P-001','name'=>'تشطيب مبنى تجريبي','type'=>'fitout','location'=>'الدمام','budget'=>220000,'contract_value'=>255000,'starts_on'=>today()->subDays(20),'ends_on'=>today()->addDays(70),'progress'=>42,'status'=>'active']);
   ProjectTask::create(['company_id'=>$sa->id,'construction_project_id'=>$project->id,'title'=>'فحص أعمال الكهرباء','priority'=>'medium','status'=>'in_progress','due_on'=>today()->addDays(3)]);
   ProjectExpense::create(['company_id'=>$sa->id,'construction_project_id'=>$project->id,'contractor_id'=>$contractor->id,'category'=>'materials','amount'=>18000,'spent_on'=>today()->subDays(5),'description'=>'مواد تجريبية']);
   Lead::create(['company_id'=>$sa->id,'name'=>'فرصة استثمارية تجريبية','interest'=>'rent','stage'=>'new','estimated_value'=>120000,'next_follow_up'=>today()->addDays(3)]);
   foreach ([['general','كيف أبدأ استخدام المنصة؟','ابدأ من العقارات والمباني ثم أضف الوحدات والمستأجرين، وبعد ذلك أنشئ عقود الإيجار والفواتير.','بدء،استخدام،منصة'],['rentals','هل النظام يوثق العقود لدى الجهات الرسمية؟','لا. هذا النظام يسجل العقود داخلياً فقط، ولا يُعتبر بديلاً عن الجهات الرسمية أو منصات التوثيق الحكومية.','عقود،توثيق،رسمي'],['finance','هل يمكن إصدار فواتير ضريبية إلكترونية؟','حالياً الفواتير داخلية، ولا يتضمن هذا الإصدار تكاملاً رسمياً مع أنظمة الفوترة الإلكترونية السعودية أو البحرينية.','ضريبة،زاتكا،فاتورة']] as [$cat,$q,$a,$keys])KnowledgeEntry::create(['company_id'=>$sa->id,'category'=>$cat,'question'=>$q,'answer'=>$a,'keywords'=>$keys,'is_published'=>true]);
   $bhp=Property::create(['company_id'=>$bh->id,'name'=>'مبنى المنامة التجريبي','category'=>'residential','country_code'=>'BH','city'=>'المنامة','status'=>'active']);
   Unit::create(['company_id'=>$bh->id,'property_id'=>$bhp->id,'code'=>'BH-01','type'=>'apartment','monthly_rent'=>385.250,'status'=>'available']);
  });
 }
 private function showcaseDemo():void {
  // Only when DEMO_DATA=true in a local test environment; all entries are fictitious.
  foreach (['demo-sa','demo-bh'] as $slug){
   $company=Company::where('slug',$slug)->first();if(!$company)continue;
   foreach (Property::where('company_id',$company->id)->get() as $property){
    $property->units()->where('status','available')->update(['is_listed'=>true,'listing_type'=>'rent']);
    if($property->units()->where('status','available')->exists())$property->update([
      'is_published'=>true,'published_at'=>$property->published_at??now(),
      'public_title'=>$property->public_title?:$property->name.' — عرض تجريبي',
      'public_summary'=>'هذا إعلان تجريبي من بيانات توضيحية غير حقيقية لغرض اختبار المنصة فقط. الأسعار والوحدات ليست عروضاً تجارية فعلية.',
    ]);
   }
  }
 }
}
