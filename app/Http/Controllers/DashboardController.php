<?php
namespace App\Http\Controllers;
use App\Models\{Property,Unit,Contact,Lease,Invoice,Payment,MaintenanceTicket,ConstructionProject,ProjectExpense,Lead,ActivityLog};
use Illuminate\Support\Carbon;
class DashboardController extends CompanyBaseController {
 public function index(){
  $c=$this->companyId();$today=now()->toDateString();
  $metrics=[
   'properties'=>Property::where('company_id',$c)->count(),
   'units'=>Unit::where('company_id',$c)->count(),
   'available'=>Unit::where('company_id',$c)->where('status','available')->count(),
   'leases'=>Lease::where('company_id',$c)->where('status','active')->count(),
   'unpaid'=>Invoice::where('company_id',$c)->whereIn('status',['unpaid','partially_paid'])->sum('amount')-Payment::where('company_id',$c)->whereHas('invoice',fn($q)=>$q->whereIn('status',['unpaid','partially_paid']))->sum('amount'),
   'payments'=>Payment::where('company_id',$c)->whereMonth('paid_on',now()->month)->whereYear('paid_on',now()->year)->sum('amount'),
   'tickets'=>MaintenanceTicket::where('company_id',$c)->whereNotIn('status',['resolved','closed'])->count(),
   'projects'=>ConstructionProject::where('company_id',$c)->where('status','active')->count(),
   'leads'=>Lead::where('company_id',$c)->where('stage','new')->count(),
  ];
  $leases=Lease::where('company_id',$c)->where('status','active')->whereDate('ends_on','>=',$today)->whereDate('ends_on','<=',now()->addDays(45)->toDateString())->orderBy('ends_on')->with(['unit','contact'])->take(5)->get();
  $tickets=MaintenanceTicket::where('company_id',$c)->whereNotIn('status',['closed','resolved'])->orderBy('due_on')->take(5)->get();
  $projects=ConstructionProject::where('company_id',$c)->whereIn('status',['active','planned'])->latest()->take(4)->get();
  $activities=ActivityLog::where('company_id',$c)->latest()->take(6)->get();
  $months=[];for($i=5;$i>=0;$i--){$d=now()->subMonths($i);$months[]=['month'=>$d->translatedFormat('M Y'),'amount'=>(float)Payment::where('company_id',$c)->whereYear('paid_on',$d->year)->whereMonth('paid_on',$d->month)->sum('amount')];}
  return view('dashboard.index',compact('metrics','leases','tickets','projects','activities','months'));
 }
}
