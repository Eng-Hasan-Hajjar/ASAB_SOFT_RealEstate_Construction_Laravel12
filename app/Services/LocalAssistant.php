<?php
namespace App\Services;
use App\Models\{Unit,Lease,MaintenanceTicket,ConstructionProject,Lead,KnowledgeEntry,Property,Company,User,Contact};
use App\Support\Workspace;
/**
 * Deterministic Arabic local assistant (NO external AI API / NO LLM).
 * All operational queries are scoped to the authenticated company by company_id.
 */
final class LocalAssistant {
 public static function normalize(string $s):string {
  $s=mb_strtolower(trim($s));
  $s=strtr($s,array_combine(preg_split('//u','٠١٢٣٤٥٦٧٨٩۰۱۲۳۴۵۶۷۸۹',-1,PREG_SPLIT_NO_EMPTY),str_split('01234567890123456789')));
  $s=preg_replace('/[\x{064B}-\x{065F}\x{0670}\x{0640}]/u','',$s);
  $s=strtr($s,['أ'=>'ا','إ'=>'ا','آ'=>'ا','ٱ'=>'ا','ى'=>'ي','ؤ'=>'و','ئ'=>'ي','ة'=>'ه']);
  $s=preg_replace('/[^\p{L}\p{N}\s]+/u',' ',$s);
  return trim(preg_replace('/\s+/u',' ',$s));
 }
 private static function matches(string $q,array $phrases):bool {
  foreach($phrases as $p) if(str_contains($q,static::normalize($p)))return true;
  return false;
 }
 public function reply(string $question,int $companyId,User $actor):array {
  $q=self::normalize($question);
  if($q==='')return $this->result('يرجى كتابة سؤال واضح.', 'empty',0);
  if($actor->getAuthIdentifier()!==auth()->id() || Workspace::companyId()!==$companyId)abort(403);
  $c=Company::findOrFail($companyId);
  $role=Workspace::role();
  $q=preg_replace('/^(?:السلام عليكم|مرحبا|اهلا|هاي|hello)\s+/u','',$q);
  if(preg_match('/^(?:ما\s*(?:هو\s*)?|شو\s*|ايش\s*|وش\s*)?(?:اسمي|اسم حسابي|اسمي المسجل|انا مين|من انا|مين انا)(?:\s|$)/u',$q)){
   return $this->result('اسم حسابك المسجّل: '.$actor->name.'.','account_name',1);
  }
  if(self::matches($q,['اسم شركتي','اسم الشركة','مساحة العمل','في اي شركة','اسم المنشأة']))
   return $this->result('أنت تعمل الآن في مساحة «'.$c->name.'».','workspace',1);
  if(self::matches($q,['ما هو دوري','شو صلاحيتي','ما صلاحياتي','نوع حسابي','وش دوري','ايش دوري'])){
   $labels=['company_admin'=>'مدير شركة','manager'=>'مدير عمليات','agent'=>'موظف عمليات','accountant'=>'محاسب','viewer'=>'قارئ','super_admin'=>'مالك المنصة'];
   return $this->result('صلاحيتك داخل هذه المساحة: '.($labels[$role]??'غير معروفة').'.','workspace_role',1);
  }
  $how=preg_match('/(?:^|\s)(?:كيف|طريقه|وين|اين|شلون|ازاي)(?:\s|$)/u',$q);
  if(AssistantReceivables::isPersonal($q) && self::matches($q,['ايجاري','مستاجر','دفعه','دفعات','مدفوعات','مستحق','ايجار','فاتور','فواتير','ذمتي','رصيد','كم علي'])){
   [$intent,$answer]=app(AssistantReceivables::class)->reply($q,$c,$role);
   return $this->result($answer,$intent,1);
  }
  if($how && self::matches($q,['اسجل دفعه','اضيف دفعه','تسجيل دفعه','اسدد فاتوره','ادفع فاتوره','تحصيل فاتوره']))
   return $this->result('من «الفواتير والتحصيل» افتح الفاتورة، واختر تسجيل دفعة وحدد المبلغ والتاريخ والحساب المالي. إذا لم يكن الزر متاحاً، اطلب من المحاسب أو المدير تنفيذها.','guide_payments',1);
  if($how && self::matches($q,['ارفع صوره','اضيف صوره','اضيف صور','اضيف غرفه','اضيف غرف','صور العقار','صور الوحده'])){
   return $this->result('افتح تعديل العقار أو الوحدة ثم «الصور والمساحات». يمكنك رفع صور عامة أو إضافة مساحة باسم ووصف وصور مستقلة. المعرض الداخلي لا يُنشر للزوار.','guide_gallery',1);
  }
  if(self::matches($q,['فواتير','مستحقات','دفعات','مدفوعات','تحصيل','متاخرات','غير مسدده','unpaid','كم علي','علي دفعه','ايجاري','مديون','رصيد المستاجر'])){
   [$intent,$answer]=app(AssistantReceivables::class)->reply($q,$c,$role);
   return $this->result($answer,$intent,1);
  }
  if($how && self::matches($q,['فلتر','فلتره','ابحث','البحث','ابحث بالجنسيه'])){
   return $this->result('افتح قسم العقارات أو الوحدات أو المستأجرين، ثم «البحث والفلاتر المتقدمة». افتح «خيارات بحث إضافية وترتيب» لعرض الفلاتر الدقيقة مثل الجنسية أو الطابق.','guide_filters',1);
  }
  if(self::matches($q,['الواجهه التفاعليه','عرض الطوابق','وين الشقق'])){
   return $this->result('من القائمة اختر «الواجهة التفاعلية»، ثم اضغط بطاقة العقار. تظهر الوحدات حسب الطابق، وبالضغط على أي وحدة تظهر تفاصيلها.','guide_explorer',1);
  }
  $money=fn($amount)=>$c->money($amount);
  if(in_array($q,['مرحبا','اهلا','السلام عليكم','هاي','hello'],true))return $this->result('أهلاً بك في مساعد عصب سوفت المحلي. يمكنني إخبارك بالوحدات المتاحة، العقود القريبة من الانتهاء، طلبات الصيانة، المشاريع، المستحقات والفرص التجارية، أو الإجابة من قاعدة المعرفة المعتمدة.','greeting',1);
  if(self::matches($q,['ماذا تستطيع','شو بتعمل','كيف اساعد','مساعده','مساعدة','اوامر','قدراتك']))return $this->result('اسألني مثلاً: كم وحدة متاحة؟ ما العقود التي تنتهي خلال 30 يوماً؟ كم طلب صيانة مفتوح؟ ما المشاريع قيد التنفيذ؟ ما قيمة الفواتير غير المسددة؟ أو: كيف أضيف عقاراً؟','help',1);
  if(self::matches($q,['وحده متاحه','وحدات متاحه','الوحدات المتاحه','شقق متاحه','وحده شاغره','وحدات شاغره','وحدات فاضيه','available units','شاغر','الشقق الفاضيه','شقق فاضيه','وحدات فارغه'])) {
   $units=Unit::where('company_id',$companyId)->occupancy('available')->with('property')->orderBy('monthly_rent')->limit(8)->get();
   $all=Unit::where('company_id',$companyId)->occupancy('available')->count();
   $lines=$units->map(fn($u)=>'• '.$u->property?->name.' / '.$u->code.' — '.$money($u->monthly_rent).' شهرياً')->implode("\n");
   return $this->result('الوحدات المتاحة حالياً: '.$all.".\n".($lines?:'لا توجد وحدات متاحة مسجلة.'),'available_units',1);
  }
  if(self::matches($q,['العقود المنتهيه','عقود انتهت','الايجارات المنتهيه','عقود خلصت'])){
   $items=Lease::where('company_id',$companyId)->where(fn($l)=>$l->where('status','ended')->orWhere(fn($l)=>$l->where('status','active')->whereDate('ends_on','<',today()->toDateString())));
   $count=(clone $items)->count();
   $sample=$items->latest('ends_on')->limit(5)->get()->map(fn($lease)=>'• '.$lease->contract_number.' — '.$lease->ends_on)->implode("\n");
   return $this->result('العقود المنتهية المسجلة: '.$count.".\n".($sample?:'لا توجد عقود منتهية مسجلة.'),'ended_leases',1);
  }
  if(self::matches($q,['عقود تنتهي','عقود قرب','انتهاء العقود','تجديد العقود','lease expiry']) || (str_contains($q,'عقود') && (str_contains($q,'تنتهي')||str_contains($q,'بتخلص')))) {
   $days=preg_match('/(?:خلال|بعد)\s+(\d{1,3})\b/u',$q,$m)?max(1,min(365,(int)$m[1])):30;
   $leases=Lease::where('company_id',$companyId)->current()->whereDate('ends_on','<=',today()->addDays($days)->toDateString());
   $count=(clone $leases)->count();
   $leases=$leases->with('unit')->orderBy('ends_on')->limit(10)->get();
   $lines=$leases->take(10)->map(fn($x)=>'• '.$x->contract_number.' — الوحدة '.$x->unit?->code.' — '.$x->ends_on)->implode("\n");
   return $this->result('العقود الحالية التي تنتهي خلال '.$days.' يوماً: '.$count.".\n".($lines?:'لا يوجد حالياً.'),'expiring_leases',1);
  }
  if(self::matches($q,['كم عقار','عدد عقارات','كم وحده','عدد الوحدات','كم مستاجر','عدد المستاجرين','ملخص المحفظه','نظره عامه'])){
   $props=Property::where('company_id',$companyId)->count();
   $unitBase=Unit::where('company_id',$companyId);
   $units=(clone $unitBase)->count();$available=(clone $unitBase)->occupancy('available')->count();$occupied=(clone $unitBase)->occupancy('occupied')->count();
   $tenants=Contact::where('company_id',$companyId)->where('type','tenant')->count();
   $current=Contact::where('company_id',$companyId)->where('type','tenant')->whereHas('activeLeases',fn($l)=>$l->where('company_id',$companyId))->count();
   return $this->result("مساحة «".$c->name."» فيها $props عقار و{$units} وحدة، منها $available متاحة و$occupied مؤجرة. المستأجرون المسجلون: {$tenants}، والمرتبطون بعقد حالي: $current.",'portfolio',1);
  }
  if(self::matches($q,['صيانة','صيانه','اعطال','اعطال','maintenance','بلاغ'])) {
   $open=MaintenanceTicket::where('company_id',$companyId)->whereNotIn('status',['resolved','closed']);
   $count=(clone $open)->count();$late=(clone $open)->whereDate('due_on','<',today()->toDateString())->count();
   $tickets=$open->orderBy('due_on')->limit(6)->get()->map(fn($x)=>'• #'.$x->id.' '.$x->title.' — '.$x->status)->implode("\n");
   return $this->result("طلبات الصيانة غير المغلقة: {$count}، والمتأخرة: $late.\n".($tickets?:'لا توجد طلبات مفتوحة.'),'maintenance',1);
  }
  if(self::matches($q,['مشاريع','مقاولات','المشاريع','انجاز','construction','مهمات المشروع'])) {
   if($role==='viewer'||$role==='agent'){
    $count=ConstructionProject::where('company_id',$companyId)->whereIn('status',['active','planned'])->count();
    return $this->result('المشاريع النشطة والمخططة المسجلة: '.$count.'.','construction',1);
   }
   $projects=ConstructionProject::where('company_id',$companyId)->whereIn('status',['active','planned'])->orderBy('name')->limit(8)->get();
   $lines=$projects->map(fn($p)=>'• '.$p->name.' — نسبة الإنجاز '.$p->progress.'% — الميزانية '.$money($p->budget))->implode("\n");
   return $this->result('المشاريع النشطة والمخططة المسجلة: '.ConstructionProject::where('company_id',$companyId)->whereIn('status',['active','planned'])->count().".\n".($lines?:'لا توجد مشاريع مسجلة.'),'construction',1);
  }
  if(self::matches($q,['عملاء محتملين','فرص','متابعه العملاء','crm','leads'])) {
   $count=Lead::where('company_id',$companyId)->whereNotIn('stage',['won','lost'])->count();
   $late=Lead::where('company_id',$companyId)->whereDate('next_follow_up','<',today()->toDateString())->whereNotIn('stage',['won','lost'])->count();
   return $this->result('الفرص التجارية المفتوحة: '.$count.'، والمتابعات المتأخرة: '.$late.'. يمكنك إدارتها من قسم «الفرص التجارية CRM».','leads',1);
  }
  if(self::matches($q,['كيف انشر عقار','نشر العقار','اعلان عقار','اضافه اعلان','عرض عقار في الموقع']))
   return $this->result('لنشر عقار في الموقع العام: (1) أضف العقار من قسم «العقارات والمباني»، (2) أضف وحدة متاحة، (3) افتح «إعلانات العقارات والموقع»، (4) اختر العقار وحدد «عرض الوحدة في السوق» واحفظ، (5) أضف الصور والوصف ثم اضغط «نشر العقار في الموقع». لا تُنشر الوحدات المؤجرة أو غير المتاحة.','publish_guide',1);
  if(self::matches($q,['كيف اضيف عقار','اضافة عقار','اضافه عقار']))return $this->result('من القائمة الجانبية اختر «العقارات والمباني» ثم «إضافة جديد»، واكتب الاسم والمدينة والدولة والتصنيف، ثم احفظ. بعدها افتح «الوحدات العقارية» لإضافة الشقق والمحلات التابعة للعقار.','guide_properties',1);
  if(self::matches($q,['كيف انشئ عقد','اضافة عقد','عقد ايجار جديد']))return $this->result('أولاً أضف العقار والوحدة والعميل. ثم افتح «عقود الإيجار» ← «عقد جديد»، واختر الوحدة والمستأجر وتاريخ البداية والنهاية وقيمة الإيجار. يتحقق النظام من التعارض عند تفعيل العقد.','guide_leases',1);
  // Published local knowledge base; bounded token score to avoid unsupported fuzzy answers.
  $entries=KnowledgeEntry::where('company_id',$companyId)->where('is_published',true)->orderBy('id')->limit(1000)->get();
  $words=array_filter(explode(' ',$q),fn($word)=>mb_strlen($word)>2);
  $best=null;$bestScore=0.0;
  foreach($entries as $entry) {
   $title=self::normalize($entry->question);$keys=self::normalize($entry->keywords??'');$text=$title.' '.$keys;
   $tokens=array_filter(explode(' ',$text),fn($word)=>mb_strlen($word)>2);
   $hits=0;foreach($words as $word)foreach($tokens as $token){if($word===$token||mb_strlen($word)>4&&str_starts_with($token,$word)){$hits++;break;}}
   $score=$title===$q?1.0:(count($words)?$hits/count($words):0);
   if($score>$bestScore){$best=$entry;$bestScore=$score;}
  }
  if($best&&$bestScore>=0.67)return $this->result($best->answer."\n\nالمصدر: قاعدة المعرفة الداخلية — #".$best->id.' ('.$best->category.').','knowledge',round($bestScore,3));
  return $this->result('لم أستطع تحديد المطلوب من السؤال. جرّب «ما اسمي في المنصة؟» أو «كم وحدة متاحة؟» أو «ما العقود التي تنتهي خلال 30 يوماً؟». للبحث المالي اذكر نوع المطلوب والفترة، ولرصيد مستأجر اذكر اسمه الكامل أو رقمه.','fallback',0);
 }
 private function result(string $answer,string $intent,float $confidence):array {return compact('answer','intent','confidence');}
}
