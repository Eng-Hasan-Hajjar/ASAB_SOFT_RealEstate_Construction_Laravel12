<?php
namespace App\Http\Controllers;
use App\Support\ModuleRegistry;
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
  return $def['model']::where('company_id',$c)->orderBy($column)->limit(500)->pluck($column,'id')->all();
 }
 private function options(array $def):array {
  $options=[];
  foreach ($def['fields'] as $key=>$field) if($field['type']==='relation') $options[$key]=$this->optionsFor($field['source']);
  return $options;
 }
 public function index(Request $request,string $module){
  $def=$this->definition($module);$q=$this->query($def);$search=trim((string)$request->query('q',''));
  if ($search!=='') {
   $columns=[];
   foreach($def['fields'] as $key=>$field) if(in_array($field['type'],['text','textarea','email'],true)) $columns[]=$key;
   if($columns) $q->where(function($s)use($columns,$search){foreach($columns as $col) $s->orWhere($col,'like','%'.$search.'%');});
  }
  $items=$q->latest('id')->paginate(12)->withQueryString();$displayOptions=$this->options($def);
  return view('modules.index',compact('module','def','items','search','displayOptions'));
 }
 public function create(Request $request,string $module){
  $this->writable($module);$def=$this->definition($module);$options=$this->options($def);$item=null;$prefill=[];
  if($module==='units' && $request->query('property_id') && \App\Models\Property::where('company_id',$this->companyId())->whereKey($request->query('property_id'))->exists())$prefill['property_id']=(int)$request->query('property_id');
  return view('modules.form',compact('module','def','options','item','prefill'));
 }
 public function edit(string $module,int $id){
  $this->writable($module);$def=$this->definition($module);$item=$this->query($def)->findOrFail($id);$options=$this->options($def);
  return view('modules.form',compact('module','def','options','item'));
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
   $rules[$key]=$r;
  }
  return $rules;
 }
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
  $data['company_id']=$this->companyId();
  $item=$def['model']::create($data);
  Audit::record('created',$module,$item->id,'إضافة '.$def['singular']);
  return redirect()->route('modules.index',$module)->with('success','تمت الإضافة بنجاح.');
 }
 public function update(Request $request,string $module,int $id){
  $this->writable($module);$def=$this->definition($module);$item=$this->query($def)->findOrFail($id);
  if($module==='units'&&$item->leases()->where('status','active')->exists()&&$request->input('status')==='available'){
   throw ValidationException::withMessages(['status'=>'لا يمكن جعل الوحدة متاحة ولها عقد إيجار نشط.']);
  }
  $item->update($this->validateData($request,$def,$module,$id));
  Audit::record('updated',$module,$item->id,'تعديل '.$def['singular']);
  return redirect()->route('modules.index',$module)->with('success','تم حفظ التعديلات.');
 }
 public function show(string $module,int $id){
  $def=$this->definition($module);abort_unless(in_array($module,['properties','projects'],true),404);
  $item=$this->query($def)->findOrFail($id);
  if($module==='properties'){
   $item->load(['units.leases']);
   $total=$item->units->count();$occupied=$item->units->where('status','occupied')->count();
   $available=$item->units->where('status','available')->count();
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
  try {$item->delete();}
  catch(QueryException $e) {return back()->withErrors(['record'=>'السجل مرتبط بمعاملات أخرى؛ يرجى تعديل حالته بدلاً من حذفه.']);}
  Audit::record('deleted',$module,$id,'حذف '.$def['singular']);
  return back()->with('success','تم حذف السجل.');
 }
}
