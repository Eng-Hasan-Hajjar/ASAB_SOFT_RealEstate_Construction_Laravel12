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
 public function create(Request $request){ $this->canWrite();$c=$this->companyId();$units=Unit::where('company_id',$c)->with('property')->withOccupancy()->orderBy('code')->get();$contacts=Contact::where('company_id',$c)->where('type','tenant')->orderBy('name')->get();$prefill=$request->validate(['unit_id'=>['nullable','integer'],'contact_id'=>['nullable','integer']]);return view('leases.create',compact('units','contacts','prefill'));}
 public function store(Request $request){
  $this->canWrite();$c=$this->companyId();
  $data=$request->validate([
   'unit_id'=>['required','integer',Rule::exists('units','id')->where('company_id',$c)],
   'contact_id'=>['required','integer',Rule::exists('contacts','id')->where('company_id',$c)->where('type','tenant')],
   'contract_number'=>['required','string','max:90',Rule::unique('leases','contract_number')->where('company_id',$c)],
   'starts_on'=>['required','date_format:Y-m-d'],'ends_on'=>['required','date_format:Y-m-d','after_or_equal:starts_on'],
   'rent_amount'=>['required','numeric','gt:0','max:999999999999.999','decimal:0,3'],'deposit'=>['nullable','numeric','min:0','max:999999999999.999','decimal:0,3'],
   'billing_cycle'=>['required',Rule::in(['monthly','quarterly','yearly'])],
   'status'=>['required',Rule::in(['draft','active','ended'])],'notes'=>['nullable','string','max:3000'],
   'legacy_receipt_number'=>['nullable','string','max:120'],
  ]);
  if($data['status']==='ended'){
   abort_unless(in_array($this->role(),['company_admin','manager'],true),403);
   if($data['ends_on']>=today()->toDateString())throw ValidationException::withMessages(['ends_on'=>'العقد التاريخي الموثّق يجب أن يكون قد انتهى قبل اليوم.']);
  }
  $lease=DB::transaction(function()use($data,$c){
   $unit=Unit::where('company_id',$c)->lockForUpdate()->findOrFail($data['unit_id']);
   if(in_array($data['status'],['active','ended'],true))$this->assertPeriod($unit,$data['starts_on'],$data['ends_on'],$data['status']==='ended');
   $lease=Lease::create([...$data,'company_id'=>$c,'created_by'=>auth()->id(),'deposit'=>$data['deposit']??0]);
   if($lease->effective_status==='active')$unit->update(['status'=>'occupied']);
   return $lease;
  });
  Audit::record('created','leases',$lease->id,'إنشاء عقد إيجار '.$lease->contract_number);
  return redirect()->route('leases.show',$lease)->with('success','تم تسجيل عقد الإيجار.');
 }
 public function show(int $lease){$record=Lease::where('company_id',$this->companyId())->with(['unit.property','contact','invoices.payments'])->findOrFail($lease);return view('leases.show',compact('record'));}
 private function assertPeriod(Unit $unit,string $from,string $to,bool $historical=false):void {
  if(!$historical && in_array($unit->status,['maintenance','inactive'],true))throw ValidationException::withMessages(['unit_id'=>'الوحدة غير قابلة للتأجير حالياً.']);
  if($unit->leases()->whereIn('status',['active','ended'])->where('starts_on','<=',$to)->where('ends_on','>=',$from)->exists()
     || $unit->reservations()->where('status','confirmed')->where('check_in','<=',$to)->where('check_out','>',$from)->exists()){
   throw ValidationException::withMessages(['unit_id'=>'الفترة تتداخل مع عقد معتمد أو حجز مؤكد.']);
  }
 }
 public function activate(int $lease){
  $this->canWrite();$c=$this->companyId();
  $stub=Lease::where('company_id',$c)->findOrFail($lease);
  DB::transaction(function()use($c,$lease,$stub){
   $unit=Unit::where('company_id',$c)->lockForUpdate()->findOrFail($stub->unit_id);
   $record=Lease::where('company_id',$c)->lockForUpdate()->findOrFail($lease);
   abort_unless($record->status==='draft',422);
   if($record->billing_cycle==='custom' || $record->rent_amount<=0 || $record->ends_on<today()->toDateString()){
    throw ValidationException::withMessages(['lease'=>'مسودة تاريخية أو منتهية. أنشئ عقداً تشغيلياً بدورية ومبلغ وفترة متحقق منها.']);
   }
   $this->assertPeriod($unit,$record->starts_on,$record->ends_on);
   $record->update(['status'=>'active']);
   if($record->effective_status==='active')$unit->update(['status'=>'occupied']);
  },3);
  Audit::record('activated','leases',$lease,'اعتماد عقد إيجار');
  return back()->with('success','تم اعتماد العقد.');
 }
 public function close(int $lease){
  $this->canWrite();$c=$this->companyId();
  $stub=Lease::where('company_id',$c)->findOrFail($lease);
  $record=DB::transaction(function()use($c,$lease,$stub){
   $unit=Unit::where('company_id',$c)->lockForUpdate()->findOrFail($stub->unit_id);
   $record=Lease::where('company_id',$c)->lockForUpdate()->findOrFail($lease);
   $wasApproved=$record->status==='active';
   $wasCurrent=$record->effective_status==='active';
   abort_unless(in_array($record->status,['active','draft'],true),422);
   $record->update(['status'=>!$wasApproved||$record->effective_status==='upcoming'?'cancelled':'ended','ends_on'=>$wasCurrent?today()->toDateString():$record->ends_on]);
   if($wasApproved && !$unit->currentLeases()->exists() && $unit->status==='occupied')$unit->update(['status'=>'available']);
   return $record;
  });
  Audit::record('closed','leases',$record->id,'إغلاق عقد '.$record->contract_number);
  return back()->with('success','تم إغلاق العقد.');
 }
}
