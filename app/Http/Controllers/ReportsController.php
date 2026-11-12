<?php
namespace App\Http\Controllers;
use App\Models\{Property,Unit,Lease,Invoice,Payment,LedgerEntry,FinancialAccount,MaintenanceTicket,ProjectExpense,Reservation};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
class ReportsController extends CompanyBaseController {
 public function index(Request $request){
  $c=$this->companyId();$data=$request->validate(['month'=>['nullable','date_format:Y-m']]);$month=$data['month']??now()->format('Y-m');
  $from=$month.'-01';$to=\Illuminate\Support\Carbon::parse($from)->endOfMonth()->toDateString();
  $income=LedgerEntry::where('company_id',$c)->where('direction','in')->whereNull('transfer_group')->whereBetween('posted_on',[$from,$to])->sum('amount');
  $expense=LedgerEntry::where('company_id',$c)->where('direction','out')->whereNull('transfer_group')->whereBetween('posted_on',[$from,$to])->sum('amount');
  $rent=LedgerEntry::where('company_id',$c)->where('direction','in')->whereNull('transfer_group')->where('category','rent')->whereBetween('posted_on',[$from,$to])->sum('amount');
  $receiptsByMethod=LedgerEntry::where('ledger_entries.company_id',$c)->where('ledger_entries.direction','in')->whereNull('ledger_entries.transfer_group')->whereBetween('ledger_entries.posted_on',[$from,$to])->join('financial_accounts','financial_accounts.id','=','ledger_entries.financial_account_id')->selectRaw('financial_accounts.type, SUM(ledger_entries.amount) amount')->groupBy('financial_accounts.type')->pluck('amount','type');
  $propertyRows=Property::where('company_id',$c)->withCount('units')->orderBy('name')->get();
  foreach($propertyRows as $property){
   $property->collected=LedgerEntry::where('company_id',$c)->where('property_id',$property->id)->where('direction','in')->whereNull('transfer_group')->whereBetween('posted_on',[$from,$to])->sum('amount');
   $property->spent=LedgerEntry::where('company_id',$c)->where('property_id',$property->id)->where('direction','out')->whereNull('transfer_group')->whereBetween('posted_on',[$from,$to])->sum('amount');
  }
  $daily=LedgerEntry::where('company_id',$c)->whereNull('transfer_group')->whereBetween('posted_on',[$from,$to])->selectRaw("posted_on, SUM(CASE WHEN direction='in' THEN amount ELSE 0 END) income, SUM(CASE WHEN direction='out' THEN amount ELSE 0 END) expense")->groupBy('posted_on')->orderBy('posted_on')->get();
  $outstanding=Invoice::where('company_id',$c)->whereNotIn('status',['paid','void'])->sum('amount')-Payment::where('company_id',$c)->whereHas('invoice',fn($q)=>$q->whereNotIn('status',['paid','void']))->sum('amount');
  $overdue=Invoice::where('company_id',$c)->whereDate('due_on','<',today())->whereNotIn('status',['paid','void'])->count();
  $unlinkedPayments=Payment::where('company_id',$c)->whereDoesntHave('ledgerEntry')->count();
  $unitsTotal=Unit::where('company_id',$c)->count();$unitsOccupied=Unit::where('company_id',$c)->where('status','occupied')->count();
  $tickets=MaintenanceTicket::where('company_id',$c)->whereNotIn('status',['closed','resolved'])->count();
  $projectsSpent=ProjectExpense::where('company_id',$c)->whereBetween('spent_on',[$from,$to])->sum('amount');
  return view('reports.index',compact('income','expense','rent','receiptsByMethod','propertyRows','daily','month','from','to','outstanding','overdue','unlinkedPayments','unitsTotal','unitsOccupied','tickets','projectsSpent'));
 }
 public function export(Request $request){
  $c=$this->companyId();$d=$request->validate(['month'=>['required','date_format:Y-m']]);$from=$d['month'].'-01';$to=\Illuminate\Support\Carbon::parse($from)->endOfMonth()->toDateString();
  $filename='asab-finance-'.$d['month'].'.csv';
  return response()->streamDownload(function()use($c,$from,$to){
   $f=fopen('php://output','w');fwrite($f,"\xEF\xBB\xBF");fputcsv($f,['التاريخ','الحساب','النوع','الفئة','العقار','الوحدة','المبلغ','السند','البيان']);
   LedgerEntry::where('company_id',$c)->whereBetween('posted_on',[$from,$to])->with(['account','property','unit'])->orderBy('posted_on')->chunk(200,function($rows)use($f){foreach($rows as $e){fputcsv($f,[$e->posted_on->format('Y-m-d'),$e->account?->name,$e->transfer_group?'تحويل':($e->direction==='in'?'إيراد':'مصروف'),$e->category,$e->property?->name,$e->unit?->code,$e->amount,$e->voucher_number,$e->description]);}});
   fclose($f);
  },$filename,['Content-Type'=>'text/csv; charset=UTF-8']);
 }
}
