<?php
namespace App\Http\Controllers;
use App\Support\{ModuleRegistry,AssetFilters};
use App\Services\WorkspaceGallery;
use App\Models\{Contact,Property,Unit};
use App\Services\Audit;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\QueryException;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ModuleController extends CompanyBaseController {
 private function definition(string $module):array {
  $def=ModuleRegistry::get($module);
  abort_unless(array_key_exists($module,ModuleRegistry::modulesForRole($this->role())),403);
  return $def;
 }
 private function writable(string $module):void {abort_unless(ModuleRegistry::canWrite($this->role(),$module),403,'دورك يسمح بالقراءة فقط في هذا القسم.');}
 private function query(array $def) {return $def['model']::query()->where('company_id',$this->companyId());}
 private function optionsFor(string $source):array {
  $c=$this->companyId();
  if ($source==='users') return User::where('company_id',$c)->where('is_active',true)->orderBy('name')->pluck('name','id')->all();
  $def=ModuleRegistry::get($source);
  $column=match($source){'units'=>'code','projects'=>'name',default=>'name'};
  return $def['model']::where('company_id',$c)->orderBy($column)->pluck($column,'id')->all();
 }
 private function options(array $def):array {
  $options=[];
  foreach ($def['fields'] as $key=>$field) if($field['type']==='relation') $options[$key]=$this->optionsFor($field['source']);
  return $options;
 }
 public function index(Request $request,string $module){
  $def=$this->definition($module);
  $companyId=$this->companyId();
  $data=$request->validate([...AssetFilters::ruleSet($module),
   'q'=>['nullable','string','max:150'],
   'f'=>['nullable','array'],
   'date_field'=>['nullable','string','max:65'],
   'date_from'=>['nullable','date_format:Y-m-d'],
   'date_to'=>['nullable','date_format:Y-m-d','after_or_equal:date_from'],
   'money_field'=>['nullable','string','max:65'],
   'money_min'=>['nullable','numeric','min:0'],
   'money_max'=>['nullable','numeric','min:0'],
   'occupancy'=>['nullable',Rule::in(['all_available','all_occupied','mixed','has_available','has_occupied','no_units'])],
   'contact_lease'=>['nullable',Rule::in(['active','not_current','former','upcoming','none','any','historical'])],
   'contact_property'=>['nullable','integer',Rule::exists('properties','id')->where('company_id',$companyId)],
  ]);
  if(isset($data['money_min'],$data['money_max']) && (float)$data['money_max'] < (float)$data['money_min']) {
   throw ValidationException::withMessages(['money_max'=>'القيمة العليا يجب أن تكون أكبر من أو تساوي القيمة الدنيا.']);
  }
  $q=$this->query($def);
  $search=trim((string)($data['q']??''));
  if ($search!=='') {
   $columns=[];
   foreach($def['fields'] as $key=>$field)if($key!=='identity_reference' && in_array($field['type'],['text','textarea','email'],true))$columns[]=$key;
   if($columns) $q->where(function($sub)use($columns,$search){foreach($columns as $col)$sub->orWhere($col,'like','%'.$search.'%');});
  }
  // Only fields explicitly declared in ModuleRegistry are eligible for filtering.
  $filterOptions=[];
  foreach($def['fields'] as $key=>$field){
   if($field['type']==='select')$filterOptions[$key]=$field['options'];
   elseif($field['type']==='relation')$filterOptions[$key]=$this->optionsFor($field['source']);
  }
  if($module==='units')$filterOptions['status']=Unit::statusLabels();
  $selectedFilters=[];
  foreach(($data['f']??[]) as $column=>$value){
   if(!is_string($column) || !array_key_exists($column,$filterOptions) || !is_scalar($value))continue;
   $value=(string)$value;
   if($value==='' || !array_key_exists($value,$filterOptions[$column]))continue;
   if($module==='units' && $column==='status')$q->occupancy($value);else $q->where($column,$value);
   $selectedFilters[$column]=$value;
  }
  $dateFields=array_filter($def['fields'],fn($field)=>$field['type']==='date');
  $moneyFields=array_filter($def['fields'],fn($field)=>in_array($field['type'],['money','number'],true));
  $dateField=$data['date_field']??array_key_first($dateFields);
  if(!array_key_exists((string)$dateField,$dateFields))$dateField=null;
  $moneyField=$data['money_field']??array_key_first($moneyFields);
  if(!array_key_exists((string)$moneyField,$moneyFields))$moneyField=null;
  if($dateField){
   if(!empty($data['date_from']))$q->whereDate($dateField,'>=',$data['date_from']);
   if(!empty($data['date_to']))$q->whereDate($dateField,'<=',$data['date_to']);
  }
  if($moneyField){
   if(isset($data['money_min']))$q->where($moneyField,'>=',(float)$data['money_min']);
   if(isset($data['money_max']))$q->where($moneyField,'<=',(float)$data['money_max']);
  }
  if($module==='properties'){
   $occupancy=$data['occupancy']??null;
   if($occupancy==='no_units')$q->whereDoesntHave('units');
   elseif($occupancy==='has_available')$q->whereHas('units',fn($r)=>$r->occupancy('available'));
   elseif($occupancy==='has_occupied')$q->whereHas('units',fn($r)=>$r->occupancy('occupied'));
   elseif($occupancy==='all_available'){
    $q->whereHas('units');
    foreach(['occupied','reserved','inactive','maintenance'] as $state)$q->whereDoesntHave('units',fn($r)=>$r->occupancy($state));
   }
   elseif($occupancy==='all_occupied'){
    $q->whereHas('units');
    foreach(['available','reserved','inactive','maintenance'] as $state)$q->whereDoesntHave('units',fn($r)=>$r->occupancy($state));
   }
   elseif($occupancy==='mixed')$q->whereHas('units',fn($r)=>$r->occupancy('available'))->whereHas('units',fn($r)=>$r->occupancy('occupied'));
   $q->withCount(['units','units as available_units_count'=>fn($r)=>$r->occupancy('available'),'units as occupied_units_count'=>fn($r)=>$r->occupancy('occupied')]);
  }
  if($module==='units')$q->withOccupancy();
  if($module==='contacts'){
   $filter=$data['contact_lease']??null;
   $location=function($lease)use($data,$companyId){
    $lease->where('leases.company_id',$companyId);
    if(!empty($data['contact_property'])||!empty($data['asset_unit']))$lease->whereHas('unit',function($unit)use($data,$companyId){
     $unit->where('company_id',$companyId);
     if(!empty($data['contact_property']))$unit->where('property_id',$data['contact_property']);
     if(!empty($data['asset_unit']))$unit->where('code','like','%'.$data['asset_unit'].'%');
    });
   };
   $hasLocation=!empty($data['contact_property'])||!empty($data['asset_unit']);
   if($filter==='active')$q->whereHas('activeLeases',$location);
   elseif($filter==='any'||($hasLocation&&!$filter))$q->whereHas('leases',$location);
   elseif($filter==='none')$q->whereDoesntHave('leases',$location);
   elseif($filter==='not_current')$q->whereDoesntHave('activeLeases',$location);
   elseif($filter==='upcoming')$q->whereHas('leases',function($l)use($location){$location($l);$l->where('status','active')->where('starts_on','>',today()->toDateString());});
   elseif($filter==='former')$q->whereDoesntHave('activeLeases',$location)->whereHas('leases',function($l)use($location){$location($l);$l->where(fn($x)=>$x->where('status','ended')->orWhere(fn($x)=>$x->where('status','active')->where('ends_on','<',today()->toDateString())));});
   elseif($filter==='historical')$q->whereHas('rentalHistory',function($h)use($data,$companyId){
    $h->where('company_id',$companyId)->visibleHistory();
    if(!empty($data['contact_property']))$h->where('property_id',$data['contact_property']);
    if(!empty($data['asset_unit']))$h->whereHas('unit',fn($u)=>$u->where('company_id',$companyId)->where('code','like','%'.$data['asset_unit'].'%'));
   });
   $q->withExists('activeLeases');
  }
  AssetFilters::apply($q,$module,$data);
  $items=$q->latest('id')->paginate(15)->withQueryString();
  $displayOptions=$this->options($def);
  $contactProperties=$module==='contacts'?\App\Models\Property::where('company_id',$companyId)->orderBy('name')->pluck('name','id')->all():[];
  return view('modules.index',compact('module','def','items','search','displayOptions','filterOptions','selectedFilters','dateFields','moneyFields','dateField','moneyField','data','contactProperties'));
 }

 // Safe server-side detail view: every lookup is restricted to the user's workspace.
 public function preview(string $module,int $id){
  $def=$this->definition($module);
  $query=$this->query($def);
  if($module==='units')$query->withOccupancy();
  $item=$query->findOrFail($id);
  $values=[];$gallery=app(WorkspaceGallery::class);$spaces=collect();$images=collect();$leases=collect();$assetUnits=collect();$historical=collect();
  $companyId=$this->companyId();
  if($item instanceof Property || $item instanceof Unit){
   $spaces=$gallery->spaces($item);$images=$gallery->images($item);
   if($item instanceof Property)$assetUnits=$item->units()->where('company_id',$companyId)->withOccupancy()->orderBy('code')->limit(12)->get();
  }
  if($item instanceof Contact || $item instanceof Unit){
   $historical=$item->rentalHistory()->where('company_id',$companyId)->visibleHistory()->whereNotNull('contact_id')
    ->with(['unit:id,code','property:id,name','contact:id,name'])->orderByDesc('starts_on')->orderByDesc('id')->limit(8)->get();
   $leases=$item->leases()->where('company_id',$companyId)->with(['unit.property','contact'])
    ->orderByRaw("CASE WHEN status='active' AND starts_on <= ? AND ends_on >= ? THEN 0 ELSE 1 END",[today()->toDateString(),today()->toDateString()])
    ->orderByDesc('starts_on')->limit(12)->get();
  }
  foreach($def['fields'] as $fieldName=>$field){
   $raw=$fieldName==='identity_reference' && !in_array($this->role(),['company_admin','manager'],true) ? 'محجوب وفق صلاحيات الوصول' : ($module==='units' && $fieldName==='status' ? Unit::statusLabels()[$item->occupancy_status] : $item->{$fieldName});
   if($field['type']==='relation' && $raw!==null){
    $source=$field['source'];
    $model=$source==='users'?User::class:ModuleRegistry::get($source)['model'];
    $related=$model::where('company_id',$this->companyId())->find($raw);
    $column=match($source){'units'=>'code',default=>'name'};
    $raw=$related?->{$column}??'غير متوفر';
   }elseif($field['type']==='select' && $raw!==null){
    $raw=$field['options'][(string)$raw]??$raw;
   }elseif($field['type']==='money' && $raw!==null){
    $raw=\App\Support\Workspace::company()->money($raw);
   }elseif($fieldName==='identity_reference' && !in_array($this->role(),['company_admin','manager'],true)){
    $raw='محجوب وفق صلاحيات الوصول';
   }
   $values[]=['label'=>$field['label'],'value'=>$raw===null||$raw===''?'—':(string)$raw];
  }
  // Show significant operational metadata not represented by the generic edit form.
  $extraFields=match($module){
   'properties'=>['is_published'=>'منشور للزوار','is_featured'=>'مميز','public_title'=>'عنوان الإعلان','public_summary'=>'ملخص الإعلان','published_at'=>'تاريخ النشر','latitude'=>'خط العرض','longitude'=>'خط الطول'],
   'units'=>['is_listed'=>'الوحدة معروضة','listing_type'=>'نوع الإعلان','furnished'=>'مفروشة','sale_price'=>'سعر البيع'],
   default=>[],
  };
  foreach($extraFields as $key=>$label){
   if(!array_key_exists($key,$item->getAttributes()))continue;
   $value=$item->{$key};
   if(is_bool($value))$value=$value?'نعم':'لا';
   elseif($value instanceof \DateTimeInterface)$value=$value->format('Y-m-d H:i');
   $values[]=['label'=>$label,'value'=>$value===null||$value===''?'—':(string)$value];
  }
  return view(request()->ajax()?'modules.preview':'modules.detail-page',compact('def','item','module','values','spaces','images','leases','assetUnits','historical'));
 }
 public function create(Request $request,string $module){
  $this->writable($module);$def=$this->definition($module);$options=$this->options($def);$item=null;$prefill=[];
  if($module==='units' && $request->query('property_id') && \App\Models\Property::where('company_id',$this->companyId())->whereKey($request->query('property_id'))->exists())$prefill['property_id']=(int)$request->query('property_id');
  return view('modules.form',compact('module','def','options','item','prefill'));
 }
 public function edit(string $module,int $id){
  $this->writable($module);$def=$this->definition($module);$item=$this->query($def)->findOrFail($id);$options=$this->options($def);
  $gallery=app(WorkspaceGallery::class);
  $spaces=($item instanceof Property || $item instanceof Unit)?$gallery->spaces($item):collect();
  $images=($item instanceof Property || $item instanceof Unit)?$gallery->images($item):collect();
  return view('modules.form',compact('module','def','options','item','spaces','images'));
 }
 private function rules(array $def):array {
  $rules=[];$company=$this->companyId();
  foreach ($def['fields'] as $key=>$field) {
   $r=[$field['required']??false?'required':'nullable'];
   switch($field['type']) {
    case 'text':$r[]='string';$r[]='max:255';break;
    case 'textarea':$r[]='string';$r[]='max:5000';break;
    case 'email':$r[]='email';$r[]='max:255';break;
    case 'date':$r[]='date_format:Y-m-d';break;
    case 'money':$r[]='numeric';$r[]='min:0';$r[]='decimal:0,3';break;
    case 'progress':$r[]='integer';$r[]='between:0,100';break;
    case 'number':$r[]='numeric';$r[]='between:-10,100000000';break;
    case 'select':$r[]=Rule::in(array_keys($field['options']));break;
    case 'relation':
     $table=$field['source']==='users'?'users':(new (ModuleRegistry::get($field['source'])['model']))->getTable();
     $r[]='integer';$r[]=Rule::exists($table,'id')->where(fn($q)=>$q->where('company_id',$company));break;
   }
   if($key==='identity_reference' && !in_array($this->role(),['company_admin','manager'],true)) continue;
   $rules[$key]=match($key){
    'city','district','nationality'=>['nullable','string','max:100',...(($field['required']??false)?['required']:[])],
    'code'=>[...$r,'max:'.($this->definitionLength($def))],
    'phone'=>[...$r,'max:40'],
    'identity_reference'=>[...$r,'max:120'],
    'rooms','bathrooms'=>['nullable','integer','between:0,300'],
    'floor'=>['nullable','integer','between:-50,300'],
    'area'=>['nullable','numeric','min:0','max:9999999999','decimal:0,2'],
    default=>$r,
   };
  }
  return $rules;
 }
 private function definitionLength(array $def):int {return $def['model']===Unit::class?50:90;}
 private function validateData(Request $request,array $def,string $module,?int $id):array {
  $data=$request->validate($this->rules($def));$company=$this->companyId();
  if($module==='units') {
   if(!empty($data['property_id'])&&isset($data['code'])){
    $q=\App\Models\Unit::where('company_id',$company)->where('property_id',$data['property_id'])->where('code',$data['code']);
    if($id)$q->whereKeyNot($id);
    if($q->exists())throw ValidationException::withMessages(['code'=>'رمز الوحدة موجود مسبقاً في هذا العقار.']);
   }
  }
  if($module==='projects'&&isset($data['code'])){
   $q=\App\Models\ConstructionProject::where('company_id',$company)->where('code',$data['code']);
   if($id)$q->whereKeyNot($id);
   if($q->exists())throw ValidationException::withMessages(['code'=>'رمز المشروع مستخدم مسبقاً.']);
  }
  if($module==='tickets'&&!empty($data['unit_id'])){
   $unit=\App\Models\Unit::where('company_id',$company)->findOrFail($data['unit_id']);
   if((int)$unit->property_id!==(int)$data['property_id'])throw ValidationException::withMessages(['unit_id'=>'الوحدة لا تنتمي للعقار المحدد.']);
  }
  if($module==='properties'&&isset($data['country_code'])){
   $company=\App\Support\Workspace::company();
   if($data['country_code']!==$company->country_code)throw ValidationException::withMessages(['country_code'=>'في هذا الإصدار، يجب أن تتطابق دولة العقار مع دولة الشركة وعملتها. أنشئ شركة منفصلة لكل دولة.']);
  }
  if($module==='knowledge')$data['is_published']=(bool)($data['is_published']??false);
  return $data;
 }
 public function store(Request $request,string $module){
  $this->writable($module);$def=$this->definition($module);$data=$this->validateData($request,$def,$module,null);
  $item=$this->saveRecord($request,$def,$module,$data,null);
  Audit::record('created',$module,$item->id,'إضافة '.$def['singular']);
  return redirect()->route('modules.index',$module)->with('success','تمت الإضافة بنجاح.');
 }
 public function update(Request $request,string $module,int $id){
  $this->writable($module);$def=$this->definition($module);
  $this->query($def)->findOrFail($id);
  $item=$this->saveRecord($request,$def,$module,$this->validateData($request,$def,$module,$id),$id);
  Audit::record('updated',$module,$item->id,'تعديل '.$def['singular']);
  return redirect()->route('modules.index',$module)->with('success','تم حفظ التعديلات.');
 }
 private function saveRecord(Request $request,array $def,string $module,array $data,?int $id){
  $gallery=app(WorkspaceGallery::class);$asset=in_array($module,['units','properties'],true);
  $files=$asset?$gallery->validate($request):[];$created=[];$removed=[];
  try {
   $item=DB::transaction(function()use($def,$module,$data,$id,$asset,$files,$gallery,&$created,&$removed){
    if($id===null)$item=$def['model']::create($data+['company_id'=>$this->companyId()]);
    else{
     $item=$this->query($def)->lockForUpdate()->findOrFail($id);
     if($item instanceof Unit){
      if($item->currentLeases()->exists() && $data['status']!=='occupied')throw ValidationException::withMessages(['status'=>'الوحدة مؤجرة بعقد حالي، أنهِ العقد أولاً لتغيير الحالة.']);
      if((int)$item->property_id!==(int)$data['property_id']&&($item->leases()->exists()||$item->reservations()->exists()
       || DB::table('asab_rental_history')->where('unit_id',$item->id)->exists()|| DB::table('asab_financial_history')->where('unit_id',$item->id)->exists()))
       throw ValidationException::withMessages(['property_id'=>'لا يمكن نقل وحدة مرتبطة بتاريخ مالي أو عقد أو حجز.']);
     }
     $item->update($data);
    }
    if($asset)$gallery->save($item,$files,$created,$removed);
    return $item;
   });
  }catch(\Throwable $error){$gallery->cleanup($created);throw $error;}
  $gallery->cleanup($removed);
  return $item;
 }
 public function show(string $module,int $id){
  $def=$this->definition($module);if(in_array($module,['units','contacts'],true))return $this->preview($module,$id);
  abort_unless(in_array($module,['properties','projects'],true),404);
  $item=$this->query($def)->findOrFail($id);
  if($module==='properties'){
   $item->load(['units'=>fn($q)=>$q->withOccupancy()->withCount('leases')]);
   $total=$item->units->count();$occupied=$item->units->where('occupancy_status','occupied')->count();
   $available=$item->units->where('occupancy_status','available')->count();
   return view('modules.show-property',compact('item','module','total','occupied','available'));
  }
  $item->load(['tasks','expenses','property']);
  $spent=(float)$item->expenses->sum('amount');$budget=(float)$item->budget;
  $remaining=$budget-$spent;
  return view('modules.show-project',compact('item','module','spent','budget','remaining'));
 }
 public function destroy(string $module,int $id){
  abort_unless(in_array($this->role(),['company_admin','manager'],true),403);
  $def=$this->definition($module);$item=$this->query($def)->findOrFail($id);
  // Financial records are immutable and are never deleted through the generic module.
  if($item instanceof Contact && DB::table('asab_rental_history')->where('company_id',$item->company_id)->where('contact_id',$id)->exists())
   return back()->withErrors(['record'=>'لا يمكن حذف مستأجر مرتبط بسجل إيجار تاريخي.']);
  try {$item->delete();}
  catch(QueryException $e) {return back()->withErrors(['record'=>'السجل مرتبط بمعاملات أخرى؛ يرجى تعديل حالته بدلاً من حذفه.']);}
  Audit::record('deleted',$module,$id,'حذف '.$def['singular']);
  return back()->with('success','تم حذف السجل.');
 }
}
