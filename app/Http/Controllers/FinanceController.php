<?php
namespace App\Http\Controllers;
use App\Models\{FinancialAccount,LedgerEntry,Property,Unit,Contact};
use App\Services\Audit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
class FinanceController extends CompanyBaseController {
 private function canWrite():void {abort_unless(in_array($this->role(),['company_admin','manager','accountant'],true),403);}
 public function index(Request $request){
  $c=$this->companyId();$from=$request->query('from',now()->startOfMonth()->toDateString());$to=$request->query('to',now()->toDateString());
  $filter=$request->validate(['from'=>['date_format:Y-m-d'],'to'=>['date_format:Y-m-d','after_or_equal:from'],'account'=>['nullable','integer',Rule::exists('financial_accounts','id')->where('company_id',$c)]]);
  $from=$filter['from']??$from;$to=$filter['to']??$to;
  $base=LedgerEntry::where('company_id',$c)->whereBetween('posted_on',[$from,$to]);
  if(!empty($filter['account']))$base->where('financial_account_id',$filter['account']);
  $income=(clone $base)->where('direction','in')->whereNull('transfer_group')->sum('amount');
  $expense=(clone $base)->where('direction','out')->whereNull('transfer_group')->sum('amount');
  $entries=(clone $base)->with(['account','property','unit'])->orderByDesc('posted_on')->orderByDesc('id')->paginate(18)->withQueryString();
  $accounts=FinancialAccount::where('company_id',$c)->withSum(['entries as total_in'=>fn($q)=>$q->where('direction','in')],'amount')->withSum(['entries as total_out'=>fn($q)=>$q->where('direction','out')],'amount')->orderBy('id')->get();
  $properties=Property::where('company_id',$c)->orderBy('name')->get();$units=Unit::where('company_id',$c)->with('property')->orderBy('code')->get();
  $contacts=Contact::where('company_id',$c)->orderBy('name')->limit(500)->get();
  return view('finance.index',compact('accounts','entries','income','expense','from','to','filter','properties','units','contacts'));
 }
 public function account(Request $request){
  abort_unless(in_array($this->role(),['company_admin','manager'],true),403);
  $c=$this->companyId();$data=$request->validate([
   'name'=>['required','string','max:150',Rule::unique('financial_accounts','name')->where('company_id',$c)],
   'type'=>['required',Rule::in(['cash','bank'])],'bank_name'=>['nullable','string','max:100'],
   'opening_balance'=>['required','numeric','decimal:0,3'],
  ]);
  $currency=\App\Support\Workspace::company()->currency;
  $acc=FinancialAccount::create([...$data,'company_id'=>$c,'currency'=>$currency,'is_active'=>true]);
  Audit::record('created','financial_accounts',$acc->id,'إضافة حساب مالي '.$acc->name);
  return back()->with('success','تمت إضافة الحساب المالي.');
 }
 public function entry(Request $request){
  $this->canWrite();$c=$this->companyId();
  $rules=[
   'financial_account_id'=>['required','integer',Rule::exists('financial_accounts','id')->where('company_id',$c)->where('is_active',true)],
   'direction'=>['required',Rule::in(['in','out','transfer'])],
   'category'=>['required',Rule::in(['rent','maintenance','utilities','salary','supplies','commission','deposit','refund','general','other'])],
   'amount'=>['required','numeric','gt:0','decimal:0,3'],'posted_on'=>['required','date_format:Y-m-d'],
   'property_id'=>['nullable','integer',Rule::exists('properties','id')->where('company_id',$c)],
   'unit_id'=>['nullable','integer',Rule::exists('units','id')->where('company_id',$c)],
   'contact_id'=>['nullable','integer',Rule::exists('contacts','id')->where('company_id',$c)],
   'voucher_number'=>['nullable','string','max:120'],'bank_reference'=>['nullable','string','max:120'],'description'=>['nullable','string','max:2000'],
   'to_account_id'=>['required_if:direction,transfer','nullable','integer',Rule::exists('financial_accounts','id')->where('company_id',$c)->where('is_active',true)],
  ];$data=$request->validate($rules);
  if($data['direction']==='transfer' && (int)$data['financial_account_id']===(int)$data['to_account_id'])throw ValidationException::withMessages(['to_account_id'=>'اختر حساباً مختلفاً للتحويل.']);
  if(!empty($data['unit_id'])){
   $unit=Unit::where('company_id',$c)->findOrFail($data['unit_id']);
   if(!empty($data['property_id']) && $unit->property_id!==(int)$data['property_id'])throw ValidationException::withMessages(['unit_id'=>'الوحدة لا تتبع العقار المحدد.']);
   $data['property_id']=$unit->property_id;
  }
  DB::transaction(function()use($data,$c){
   $shared=collect($data)->only(['posted_on','amount','category','property_id','unit_id','contact_id','voucher_number','bank_reference','description'])->all();
   if($data['direction']==='transfer'){
    $group=(string)Str::uuid();$shared['category']='transfer';
    foreach([[$data['financial_account_id'],'out'],[$data['to_account_id'],'in']] as [$account,$direction])
     LedgerEntry::create([...$shared,'company_id'=>$c,'financial_account_id'=>$account,'direction'=>$direction,'transfer_group'=>$group,'created_by'=>auth()->id()]);
   }else{
    LedgerEntry::create([...$shared,'company_id'=>$c,'financial_account_id'=>$data['financial_account_id'],'direction'=>$data['direction'],'created_by'=>auth()->id()]);
   }
  });
  Audit::record('created','ledger_entries',null,'تسجيل حركة مالية');
  return back()->with('success','تم قيد الحركة المالية.');
 }
}
