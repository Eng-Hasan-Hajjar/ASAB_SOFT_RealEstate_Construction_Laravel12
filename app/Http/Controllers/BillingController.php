<?php
namespace App\Http\Controllers;
use App\Models\{Lease,Invoice};
use App\Services\Audit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Carbon;
class BillingController extends CompanyBaseController {
 public function index(Request $request){
  $c=$this->companyId();$data=$request->validate(['month'=>['nullable','date_format:Y-m']]);$month=$data['month']??now()->format('Y-m');
  $from=Carbon::parse($month.'-01');$to=$from->copy()->endOfMonth();
  $leases=Lease::where('company_id',$c)->where('status','active')->where('billing_cycle','monthly')->whereDate('starts_on','<=',$from)->whereDate('ends_on','>=',$to)->with(['unit.property','contact'])->orderBy('unit_id')->get();
  $already=Invoice::where('company_id',$c)->whereIn('lease_id',$leases->pluck('id'))->whereDate('period_start',$from->toDateString())->pluck('lease_id')->all();
  return view('billing.index',compact('leases','already','month','from','to'));
 }
 public function generate(Request $request){
  abort_unless(in_array($this->role(),['company_admin','manager','accountant'],true),403);
  $c=$this->companyId();$data=$request->validate(['month'=>['required','date_format:Y-m']]);
  $from=Carbon::parse($data['month'].'-01')->startOfMonth();$to=$from->copy()->endOfMonth();$generated=0;
  DB::transaction(function()use($c,$from,$to,&$generated){
   $leases=Lease::where('company_id',$c)->where('status','active')->where('billing_cycle','monthly')->whereDate('starts_on','<=',$from)->whereDate('ends_on','>=',$to)->get();
   foreach($leases as $lease){
    // Use actual lease-day anchor, clamped to this month's last day.
    $dueDay=min((int)Carbon::parse($lease->starts_on)->day,(int)$to->day);
    $due=Carbon::parse($from->toDateString())->day($dueDay);
    if($due->lt(Carbon::parse($lease->starts_on)))$due=Carbon::parse($lease->starts_on);
    $model=Invoice::firstOrCreate(['lease_id'=>$lease->id,'period_start'=>$from->toDateString()],[
      'company_id'=>$c,'contact_id'=>$lease->contact_id,'number'=>'RENT-'.$lease->id.'-'.$from->format('Ym'),
      'issued_on'=>now()->toDateString(),'due_on'=>$due->toDateString(),'period_end'=>$to->toDateString(),
      'amount'=>$lease->rent_amount,'status'=>'unpaid','origin'=>'monthly_generator','description'=>'استحقاق إيجار شهر '.$from->format('Y-m')]);
    if($model->wasRecentlyCreated)$generated++;
   }
  });Audit::record('created','monthly_invoices',null,'توليد '.$generated.' استحقاق إيجار');
  return redirect()->route('billing.index',['month'=>$data['month']])->with('success','تم إنشاء '.$generated.' استحقاق جديد، مع تجاوز الفواتير الموجودة مسبقاً لهذا الشهر.');
 }
}
