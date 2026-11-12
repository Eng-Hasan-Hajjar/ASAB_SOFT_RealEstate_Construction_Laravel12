<?php
namespace App\Http\Controllers;
use App\Models\{Lease,Unit,Contact};
use App\Services\Audit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
class LeaseController extends CompanyBaseController {
 private function canWrite():void {abort_unless(in_array($this->role(),['company_admin','manager','agent'],true),403);}
 public function index(Request $request){$c=$this->companyId();$leases=Lease::where('company_id',$c)->with(['unit.property','contact'])->latest('id')->paginate(12);return view('leases.index',compact('leases'));}
 public function create(){ $this->canWrite();$c=$this->companyId();$units=Unit::where('company_id',$c)->with('property')->orderBy('code')->get();$contacts=Contact::where('company_id',$c)->orderBy('name')->get();return view('leases.create',compact('units','contacts'));}
 public function store(Request $request){
  $this->canWrite();$c=$this->companyId();
  $data=$request->validate([
   'unit_id'=>['required','integer',Rule::exists('units','id')->where('company_id',$c)],
   'contact_id'=>['required','integer',Rule::exists('contacts','id')->where('company_id',$c)],
   'contract_number'=>['required','string','max:90',Rule::unique('leases','contract_number')->where('company_id',$c)],
   'starts_on'=>['required','date_format:Y-m-d'],'ends_on'=>['required','date_format:Y-m-d','after:starts_on'],
   'rent_amount'=>['required','numeric','gt:0','decimal:0,3'],'deposit'=>['nullable','numeric','min:0','decimal:0,3'],
   'billing_cycle'=>['required',Rule::in(['monthly','quarterly','yearly'])],
   'status'=>['required',Rule::in(['draft','active'])],'notes'=>['nullable','string','max:3000'],
  ]);
  $lease=DB::transaction(function()use($data,$c){
   $unit=Unit::where('company_id',$c)->lockForUpdate()->findOrFail($data['unit_id']);
   if($data['status']==='active'){
    if($unit->status==='maintenance'||$unit->status==='inactive')throw ValidationException::withMessages(['unit_id'=>'الوحدة غير قابلة للتأجير حالياً.']);
    $conflict=Lease::where('company_id',$c)->where('unit_id',$unit->id)->where('status','active')->where('starts_on','<=',$data['ends_on'])->where('ends_on','>=',$data['starts_on'])->exists();
    if($conflict)throw ValidationException::withMessages(['unit_id'=>'توجد مدة متداخلة مع عقد نشط على هذه الوحدة.']);
   }
   $lease=Lease::create([...$data,'company_id'=>$c,'created_by'=>auth()->id(),'deposit'=>$data['deposit']??0]);
   if($data['status']==='active')$unit->update(['status'=>'occupied']);
   return $lease;
  });
  Audit::record('created','leases',$lease->id,'إنشاء عقد إيجار '.$lease->contract_number);
  return redirect()->route('leases.show',$lease)->with('success','تم تسجيل عقد الإيجار.');
 }
 public function show(int $lease){$record=Lease::where('company_id',$this->companyId())->with(['unit.property','contact','invoices.payments'])->findOrFail($lease);return view('leases.show',compact('record'));}
 public function close(int $lease){
  $this->canWrite();$c=$this->companyId();
  $record=DB::transaction(function()use($c,$lease){
   $record=Lease::where('company_id',$c)->lockForUpdate()->findOrFail($lease);
   abort_unless(in_array($record->status,['active','draft'],true),422);
   $record->update(['status'=>'ended']);
   $unit=Unit::where('company_id',$c)->lockForUpdate()->findOrFail($record->unit_id);
   $hasCurrent=Lease::where('company_id',$c)->where('unit_id',$unit->id)->where('status','active')->whereDate('starts_on','<=',today()->toDateString())->whereDate('ends_on','>=',today()->toDateString())->exists();
   if(!$hasCurrent&&$unit->status==='occupied')$unit->update(['status'=>'available']);
   return $record;
  });
  Audit::record('closed','leases',$record->id,'إغلاق عقد '.$record->contract_number);
  return back()->with('success','تم إغلاق العقد.');
 }
}
