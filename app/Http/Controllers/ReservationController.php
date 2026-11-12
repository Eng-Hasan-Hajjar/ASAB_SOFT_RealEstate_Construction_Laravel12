<?php
namespace App\Http\Controllers;
use App\Models\{Reservation,Unit,Contact,Lease};
use App\Services\Audit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
class ReservationController extends CompanyBaseController {
 private function canWrite():void{abort_unless(in_array($this->role(),['company_admin','manager','agent'],true),403);}
 public function index(){
  $c=$this->companyId();$reservations=Reservation::where('company_id',$c)->with(['unit.property','contact'])->latest('id')->paginate(15);
  $units=Unit::where('company_id',$c)->with('property')->orderBy('code')->get();$contacts=Contact::where('company_id',$c)->orderBy('name')->get();
  return view('reservations.index',compact('reservations','units','contacts'));
 }
 public function store(Request $request){
  $this->canWrite();$c=$this->companyId();$data=$request->validate([
   'unit_id'=>['required','integer',Rule::exists('units','id')->where('company_id',$c)],
   'contact_id'=>['required','integer',Rule::exists('contacts','id')->where('company_id',$c)],
   'check_in'=>['required','date_format:Y-m-d'],'check_out'=>['required','date_format:Y-m-d','after:check_in'],
   'total_price'=>['required','numeric','min:0','decimal:0,3'],'status'=>['required',Rule::in(['pending','confirmed'])],
   'notes'=>['nullable','string','max:2000']]);
  $reservation=DB::transaction(function()use($c,$data){
   $unit=Unit::where('company_id',$c)->lockForUpdate()->findOrFail($data['unit_id']);
   if($unit->status==='inactive'||$unit->status==='maintenance')throw ValidationException::withMessages(['unit_id'=>'الوحدة غير قابلة للحجز.']);
   if($data['status']==='confirmed')$this->assertNoConflict($c,$unit->id,$data['check_in'],$data['check_out']);
   return Reservation::create([...$data,'company_id'=>$c,'reference'=>'RSV-'.now()->format('ymd').'-'.strtoupper(bin2hex(random_bytes(4)))]);
  });Audit::record('created','reservations',$reservation->id,'حجز وحدة');
  return back()->with('success','تم تسجيل الحجز.');
 }
 private function assertNoConflict(int $c,int $unit,string $from,string $to,?int $exclude=null):void{
  // Reservations use half-open [check_in, check_out): checkout permits new check-in that day.
  $bookings=Reservation::where('company_id',$c)->where('unit_id',$unit)->where('status','confirmed')->whereDate('check_in','<',$to)->whereDate('check_out','>',$from);
  if($exclude)$bookings->whereKeyNot($exclude);
  $leases=Lease::where('company_id',$c)->where('unit_id',$unit)->where('status','active')->whereDate('starts_on','<',$to)->whereDate('ends_on','>=',$from);
  if($bookings->exists()||$leases->exists())throw ValidationException::withMessages(['unit_id'=>'الفترة تتعارض مع حجز مؤكد أو عقد نشط على الوحدة.']);
 }
 public function status(Request $request,int $reservation){
  $this->canWrite();$c=$this->companyId();$data=$request->validate(['status'=>['required',Rule::in(['confirmed','cancelled','completed'])]]);
  DB::transaction(function()use($c,$reservation,$data){
   $record=Reservation::where('company_id',$c)->lockForUpdate()->findOrFail($reservation);
   abort_unless(in_array($record->status,['pending','confirmed'],true),422,'لا يمكن تغيير حجز مغلق.');
   $unit=Unit::where('company_id',$c)->lockForUpdate()->findOrFail($record->unit_id);
   if($data['status']==='confirmed')$this->assertNoConflict($c,$unit->id,$record->check_in->format('Y-m-d'),$record->check_out->format('Y-m-d'),$record->id);
   $record->update(['status'=>$data['status']]);
  });Audit::record('updated','reservations',$reservation,'تغيير حالة حجز');return back()->with('success','تم تحديث حالة الحجز.');
 }
}
