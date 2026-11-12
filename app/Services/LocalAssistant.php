<?php
namespace App\Services;
use App\Models\{Unit,Lease,MaintenanceTicket,ConstructionProject,ProjectTask,Invoice,Payment,Lead,KnowledgeEntry,Property};
use Illuminate\Support\Str;
/**
 * Deterministic Arabic local assistant (NO external AI API / NO LLM).
 * All operational queries are scoped to the authenticated company by company_id.
 */
final class LocalAssistant {
 public static function normalize(string $s):string {
  $s=mb_strtolower(trim($s));
  $s=preg_replace('/[\x{064B}-\x{065F}\x{0670}\x{0640}]/u','',$s);
  $s=strtr($s,['أ'=>'ا','إ'=>'ا','آ'=>'ا','ٱ'=>'ا','ى'=>'ي','ؤ'=>'و','ئ'=>'ي','ة'=>'ه']);
  $s=preg_replace('/[^\p{Arabic}\p{Latin}\p{N}\s]+/u',' ',$s);
  return trim(preg_replace('/\s+/u',' ',$s));
 }
 private static function matches(string $q,array $phrases):bool {
  foreach($phrases as $p) if(str_contains($q,static::normalize($p)))return true;
  return false;
 }
 public function reply(string $question,int $companyId):array {
  $q=self::normalize($question);
  if($q==='')return $this->result('يرجى كتابة سؤال واضح.', 'empty',0);
  $c=\App\Models\Company::findOrFail($companyId);
  $money=fn($amount)=>$c->money($amount);
  if(self::matches($q,['مرحبا','اهلا','السلام عليكم','هاي','hello']))return $this->result('أهلاً بك في مساعد عصب سوفت المحلي. يمكنني إخبارك بالوحدات المتاحة، العقود القريبة من الانتهاء، طلبات الصيانة، المشاريع، المستحقات والفرص التجارية، أو الإجابة من قاعدة المعرفة المعتمدة.','greeting',1);
  if(self::matches($q,['ماذا تستطيع','شو بتعمل','كيف اساعد','مساعده','مساعدة','اوامر','قدراتك']))return $this->result('اسألني مثلاً: كم وحدة متاحة؟ ما العقود التي تنتهي خلال 30 يوماً؟ كم طلب صيانة مفتوح؟ ما المشاريع قيد التنفيذ؟ ما قيمة الفواتير غير المسددة؟ أو: كيف أضيف عقاراً؟','help',1);
  if(self::matches($q,['وحدات متاحه','الوحدات المتاحه','شقق متاحه','وحده شاغره','وحدات شاغره','وحدات فاضيه','available units','شاغر'])) {
   $units=Unit::where('company_id',$companyId)->where('status','available')->with('property')->orderBy('monthly_rent')->limit(8)->get();
   $all=Unit::where('company_id',$companyId)->where('status','available')->count();
   $lines=$units->map(fn($u)=>'• '.$u->property?->name.' / '.$u->code.' — '.$money($u->monthly_rent).' شهرياً')->implode("\n");
   return $this->result('الوحدات المتاحة حالياً: '.$all.".\n".($lines?:'لا توجد وحدات متاحة مسجلة.'),'available_units',1);
  }
  if(self::matches($q,['عقود تنتهي','عقود قرب','انتهاء العقود','تجديد العقود','العقود المنتهيه','lease expiry','الايجارات المنتهيه'])) {
   $leases=Lease::where('company_id',$companyId)->where('status','active')->whereDate('ends_on','>=',today()->toDateString())->whereDate('ends_on','<=',today()->addDays(30)->toDateString())->with('unit')->orderBy('ends_on')->get();
   $lines=$leases->take(10)->map(fn($x)=>'• '.$x->contract_number.' — الوحدة '.$x->unit?->code.' — '.$x->ends_on)->implode("\n");
   return $this->result('العقود النشطة التي تنتهي خلال 30 يوماً: '.$leases->count().".\n".($lines?:'لا يوجد حالياً.'),'expiring_leases',1);
  }
  if(self::matches($q,['صيانة','صيانه','اعطال','اعطال','maintenance','بلاغ'])) {
   $open=MaintenanceTicket::where('company_id',$companyId)->whereNotIn('status',['resolved','closed']);
   $count=(clone $open)->count();$late=(clone $open)->whereDate('due_on','<',today()->toDateString())->count();
   $tickets=$open->orderBy('due_on')->limit(6)->get()->map(fn($x)=>'• #'.$x->id.' '.$x->title.' — '.$x->status)->implode("\n");
   return $this->result("طلبات الصيانة غير المغلقة: $count، والمتأخرة: $late.\n".($tickets?:'لا توجد طلبات مفتوحة.'),'maintenance',1);
  }
  if(self::matches($q,['مشاريع','مقاولات','المشاريع','انجاز','construction','مهمات المشروع'])) {
   $projects=ConstructionProject::where('company_id',$companyId)->whereIn('status',['active','planned'])->orderBy('name')->limit(8)->get();
   $lines=$projects->map(fn($p)=>'• '.$p->name.' — نسبة الإنجاز '.$p->progress.'% — الميزانية '.$money($p->budget))->implode("\n");
   return $this->result('المشاريع النشطة والمخططة المسجلة: '.ConstructionProject::where('company_id',$companyId)->whereIn('status',['active','planned'])->count().".\n".($lines?:'لا توجد مشاريع مسجلة.'),'construction',1);
  }
  if(self::matches($q,['مستحقات','فواتير','دفعات','تحصيل','متاخرات','غير مسدده','unpaid'])) {
   $invoices=Invoice::where('company_id',$companyId)->whereIn('status',['unpaid','partially_paid'])->get();
   $total=round($invoices->sum(fn($i)=>$i->balance),3);
   return $this->result('الفواتير غير المسددة بالكامل: '.$invoices->count().'، وإجمالي الرصيد المتبقي: '.$money($total).'. هذه الأرقام من السجلات المسجلة في النظام وليست كشفاً مصرفياً.','unpaid_invoices',1);
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
  if(self::matches($q,['كيف اسجل دفعه','تسجيل دفعه','تحصيل فاتوره']))return $this->result('افتح «الفواتير والتحصيل»، اختر الفاتورة ثم أضف الدفعة بتاريخها وطريقتها. يمنع النظام تسجيل مبلغ أكبر من الرصيد المتبقي.','guide_payments',1);
  // Published local knowledge base; bounded token score to avoid unsupported fuzzy answers.
  $entries=KnowledgeEntry::where('company_id',$companyId)->where('is_published',true)->get();
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
  return $this->result('لا أملك معلومة موثوقة للإجابة عن هذا السؤال من البيانات المسموح لي قراءتها. جرّب صياغة أوضح، أو أضف سؤالاً وإجابة معتمدين إلى «قاعدة معرفة المساعد». لا أستخدم الإنترنت ولا أتوقع معلومات غير موجودة.','fallback',0);
 }
 private function result(string $answer,string $intent,float $confidence):array {return compact('answer','intent','confidence');}
}
