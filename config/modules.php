<?php
// A configuration-driven CRUD catalogue: every foreign key is validated against the logged-in company.
$t=fn(string $label,bool $required=false)=>['label'=>$label,'type'=>'text','required'=>$required];
$d=fn(string $label,bool $required=false)=>['label'=>$label,'type'=>'date','required'=>$required];
$m=fn(string $label,bool $required=false)=>['label'=>$label,'type'=>'money','required'=>$required];
$n=fn(string $label,bool $required=false)=>['label'=>$label,'type'=>'number','required'=>$required];
$a=fn(string $label)=>['label'=>$label,'type'=>'textarea'];
$s=fn(string $label,array $options,bool $required=true)=>['label'=>$label,'type'=>'select','options'=>$options,'required'=>$required];
$r=fn(string $label,string $source,bool $required=false)=>['label'=>$label,'type'=>'relation','source'=>$source,'required'=>$required];
return [
 'properties'=>['title'=>'العقارات والمباني','singular'=>'عقار','model'=>App\Models\Property::class,'icon'=>'building','description'=>'سجل الأصول العقارية في السعودية والبحرين','fields'=>[
  'name'=>$t('اسم العقار',true),'category'=>$s('تصنيف العقار',['residential'=>'سكني','commercial'=>'تجاري','mixed'=>'متعدد الاستخدامات','land'=>'أرض']),
  'country_code'=>$s('الدولة',['SA'=>'السعودية','BH'=>'البحرين']),'city'=>$t('المدينة',true),'district'=>$t('الحي'),'address'=>$t('العنوان التفصيلي'),
  'area'=>$n('المساحة (م²)'),'status'=>$s('حالة العقار',['active'=>'نشط','under_maintenance'=>'تحت الصيانة','inactive'=>'غير نشط']),'description'=>$a('الوصف والملاحظات')],
  'columns'=>['name','city','country_code','category','status']],
 'units'=>['title'=>'الوحدات العقارية','singular'=>'وحدة','model'=>App\Models\Unit::class,'icon'=>'door','description'=>'الشقق والمحلات والمكاتب وتوافرها','fields'=>[
  'property_id'=>$r('العقار','properties',true),'code'=>$t('رقم / رمز الوحدة',true),'type'=>$s('نوع الوحدة',['apartment'=>'شقة','villa'=>'فيلا','office'=>'مكتب','shop'=>'محل','warehouse'=>'مستودع','other'=>'أخرى']),
  'floor'=>$n('الطابق'),'rooms'=>$n('الغرف'),'bathrooms'=>$n('الحمامات'),'area'=>$n('المساحة (م²)'),
  'monthly_rent'=>$m('الإيجار الشهري',true),'status'=>$s('الحالة',['available'=>'متاحة','occupied'=>'مؤجرة','maintenance'=>'صيانة','inactive'=>'غير نشطة']),'description'=>$a('الملاحظات')],
  'columns'=>['code','property_id','type','monthly_rent','status']],
 'contacts'=>['title'=>'العملاء والمستأجرون','singular'=>'عميل','model'=>App\Models\Contact::class,'icon'=>'users','description'=>'المستأجرون والملاك والمشترون','fields'=>[
  'name'=>$t('الاسم الكامل',true),'type'=>$s('النوع',['tenant'=>'مستأجر','owner'=>'مالك','buyer'=>'مشتري','supplier'=>'مورد','other'=>'آخر']),
  'phone'=>$t('الجوال'),'nationality'=>$t('الجنسية'),'identity_reference'=>$t('مرجع الهوية (اختياري، داخلي)'),'email'=>['label'=>'البريد الإلكتروني','type'=>'email'],'city'=>$t('المدينة'),
  'country_code'=>$s('الدولة',['SA'=>'السعودية','BH'=>'البحرين'],false),'notes'=>$a('ملاحظات داخلية')],
  'columns'=>['name','type','phone','email','city']],
 'tickets'=>['title'=>'طلبات الصيانة','singular'=>'طلب صيانة','model'=>App\Models\MaintenanceTicket::class,'icon'=>'wrench','description'=>'متابعة البلاغات والتكليفات والمواعيد','fields'=>[
  'title'=>$t('عنوان البلاغ',true),'property_id'=>$r('العقار','properties',true),'unit_id'=>$r('الوحدة','units'),'contact_id'=>$r('مقدم الطلب','contacts'),
  'assigned_to'=>$r('الموظف المسؤول','users'),'priority'=>$s('الأولوية',['low'=>'منخفضة','medium'=>'متوسطة','high'=>'عالية','urgent'=>'عاجلة']),
  'status'=>$s('الحالة',['open'=>'مفتوح','in_progress'=>'قيد التنفيذ','waiting'=>'بانتظار طرف آخر','resolved'=>'تم الحل','closed'=>'مغلق']),
  'due_on'=>$d('تاريخ الاستحقاق'),'description'=>$a('تفاصيل البلاغ')],
  'columns'=>['title','property_id','priority','status','due_on']],
 'contractors'=>['title'=>'المقاولون والموردون','singular'=>'مقاول','model'=>App\Models\Contractor::class,'icon'=>'hardhat','description'=>'شركاء التنفيذ والموردون الفنيون','fields'=>[
  'name'=>$t('اسم المقاول / المورد',true),'specialty'=>$t('التخصص'),'phone'=>$t('الهاتف'),
  'email'=>['label'=>'البريد الإلكتروني','type'=>'email'],'notes'=>$a('بيانات إضافية')],
  'columns'=>['name','specialty','phone','email']],
 'projects'=>['title'=>'مشاريع المقاولات','singular'=>'مشروع','model'=>App\Models\ConstructionProject::class,'icon'=>'construction','description'=>'الميزانيات ومراحل التنفيذ ومراقبة الإنجاز','fields'=>[
  'code'=>$t('رمز المشروع',true),'name'=>$t('اسم المشروع',true),'type'=>$s('نوع المشروع',['general'=>'مقاولات عامة','construction'=>'بناء','fitout'=>'تشطيبات','renovation'=>'ترميم','maintenance'=>'صيانة','other'=>'أخرى']),
  'property_id'=>$r('العقار المرتبط','properties'),'contact_id'=>$r('العميل','contacts'),'location'=>$t('الموقع'),
  'budget'=>$m('الميزانية',true),'contract_value'=>$m('قيمة العقد',true),'starts_on'=>$d('تاريخ البدء'),'ends_on'=>$d('التسليم المتوقع'),
  'progress'=>['label'=>'نسبة الإنجاز %','type'=>'progress'],'status'=>$s('الحالة',['planned'=>'مخطط','active'=>'قيد التنفيذ','paused'=>'متوقف','completed'=>'منجز','cancelled'=>'ملغي']),
  'description'=>$a('وصف المشروع')],
  'columns'=>['code','name','status','progress','budget']],
 'tasks'=>['title'=>'مهام المشاريع','singular'=>'مهمة','model'=>App\Models\ProjectTask::class,'icon'=>'list','description'=>'مهام الفرق والموظفين والجداول الزمنية','fields'=>[
  'construction_project_id'=>$r('المشروع','projects',true),'title'=>$t('عنوان المهمة',true),'assigned_to'=>$r('المسؤول','users'),
  'priority'=>$s('الأولوية',['low'=>'منخفضة','medium'=>'متوسطة','high'=>'عالية','urgent'=>'عاجلة']),
  'status'=>$s('الحالة',['todo'=>'قيد الانتظار','in_progress'=>'قيد العمل','blocked'=>'معطلة','done'=>'مكتملة']),
  'due_on'=>$d('موعد الإنجاز'),'description'=>$a('التفاصيل')],
  'columns'=>['title','construction_project_id','assigned_to','status','due_on']],
 'expenses'=>['title'=>'مصروفات المشاريع','singular'=>'مصروف','model'=>App\Models\ProjectExpense::class,'icon'=>'wallet','description'=>'متابعة تكاليف المقاولات مقابل الميزانية','fields'=>[
  'construction_project_id'=>$r('المشروع','projects',true),'contractor_id'=>$r('المقاول','contractors'),
  'category'=>$s('الفئة',['materials'=>'مواد','labour'=>'عمالة','equipment'=>'معدات','subcontract'=>'مقاول باطن','transport'=>'نقل','other'=>'أخرى']),
  'amount'=>$m('المبلغ',true),'spent_on'=>$d('تاريخ الصرف',true),'description'=>$a('البيان')],
  'columns'=>['construction_project_id','category','amount','spent_on']],
 'leads'=>['title'=>'الفرص التجارية CRM','singular'=>'فرصة','model'=>App\Models\Lead::class,'icon'=>'target','description'=>'الاستفسارات وفرص الإيجار والبيع والمقاولات','fields'=>[
  'name'=>$t('اسم العميل المحتمل',true),'phone'=>$t('الجوال'),'nationality'=>$t('الجنسية'),'identity_reference'=>$t('مرجع الهوية (اختياري، داخلي)'),'email'=>['label'=>'البريد الإلكتروني','type'=>'email'],
  'interest'=>$s('نوع الاهتمام',['rent'=>'إيجار','sale'=>'شراء','construction'=>'مقاولات','management'=>'إدارة أملاك','other'=>'غير ذلك']),
  'stage'=>$s('المرحلة',['new'=>'جديد','contacted'=>'تم التواصل','qualified'=>'مؤهل','proposal'=>'عرض سعر','negotiation'=>'تفاوض','won'=>'تم التعاقد','lost'=>'مفقود']),
  'estimated_value'=>$m('القيمة المتوقعة'),'next_follow_up'=>$d('موعد المتابعة'),'assigned_to'=>$r('المسؤول','users'),'notes'=>$a('ملخص التواصل')],
  'columns'=>['name','interest','stage','next_follow_up','estimated_value']],
 'knowledge'=>['title'=>'قاعدة معرفة المساعد','singular'=>'إجابة معتمدة','model'=>App\Models\KnowledgeEntry::class,'icon'=>'brain','description'=>'أسئلة وأجوبة محلية معتمدة للمساعد الداخلي','fields'=>[
  'category'=>$s('التصنيف',['general'=>'عام','properties'=>'العقارات','rentals'=>'الإيجارات','construction'=>'المقاولات','finance'=>'المالية']),
  'question'=>$t('السؤال المعتمد',true),'answer'=>['label'=>'الإجابة المعتمدة','type'=>'textarea','required'=>true],
  'keywords'=>$t('كلمات مفتاحية، مفصولة بفاصلة'),'is_published'=>$s('إتاحة الإجابة',['1'=>'منشورة','0'=>'مخفية'])],
  'columns'=>['question','category','is_published']],
];
