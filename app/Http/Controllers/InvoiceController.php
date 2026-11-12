<?php
namespace App\Http\Controllers;
use App\Models\{Invoice,Lease,Contact,Payment,FinancialAccount,LedgerEntry};
use App\Services\Audit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
class InvoiceController extends CompanyBaseController {
 private function canWrite():void {abort_unless(in_array($this->role(),['company_admin','manager','accountant'],true),403);}
 public function index(){ $invoices=Invoice::where('company_id',$this->companyId())->with(['contact','payments'])->latest('id')->paginate(12);return view('invoices.index',compact('invoices')); }
 public function create(Request $request){$this->canWrite();$c=$this->companyId();$contacts=Contact::where('company_id',$c)->orderBy('name')->get();$leases=Lease::where('company_id',$c)->whereIn('status',['active','ended'])->orderBy('contract_number')->get();return view('invoices.create',compact('contacts','leases'));}
 public function store(Request $request){
  $this->canWrite();$c=$this->companyId();$data=$request->validate([
   'contact_id'=>['required','integer',Rule::exists('contacts','id')->where('company_id',$c)],
   'lease_id'=>['nullable','integer',Rule::exists('leases','id')->where('company_id',$c)],
   'amount'=>['required','numeric','gt:0','decimal:0,3'],'issued_on'=>['required','date_format:Y-m-d'],
   'due_on'=>['required','date_format:Y-m-d','after_or_equal:issued_on'],'description'=>['nullable','string','max:3000'],
  ]);
  if(!empty($data['lease_id'])) {
   $lease=Lease::where('company_id',$c)->findOrFail($data['lease_id']);
   if((int)$lease->contact_id!==(int)$data['contact_id'])throw ValidationException::withMessages(['contact_id'=>'المستأجر لا يطابق المستأجر المرتبط بالعقد.']);
  }
  $data['number']='INV-'.now()->format('ymd').'-'.strtoupper(bin2hex(random_bytes(4)));
  $invoice=Invoice::create([...$data,'company_id'=>$c,'status'=>'unpaid']);
  Audit::record('created','invoices',$invoice->id,'فاتورة '.$invoice->number);
  return redirect()->route('invoices.show',$invoice)->with('success','تم إصدار الفاتورة.');
 }
 public function show(int $invoice){$c=$this->companyId();$record=Invoice::where('company_id',$c)->with(['contact','lease.unit','payments'])->findOrFail($invoice);$accounts=FinancialAccount::where('company_id',$c)->where('is_active',true)->orderBy('name')->get();return view('invoices.show',compact('record','accounts'));}
 public function pay(Request $request,int $invoice){
  $this->canWrite();$c=$this->companyId();$data=$request->validate([
   'amount'=>['required','numeric','gt:0','decimal:0,3'],'paid_on'=>['required','date_format:Y-m-d'],
   'method'=>['required',Rule::in(['cash','transfer','card','other'])],
   'reference'=>['nullable','string','max:120'],'notes'=>['nullable','string','max:1000'],
   'financial_account_id'=>['required','integer',Rule::exists('financial_accounts','id')->where('company_id',$c)->where('is_active',true)],
  ]);
  $payment=DB::transaction(function()use($c,$invoice,$data){
   $record=Invoice::where('company_id',$c)->lockForUpdate()->findOrFail($invoice);
   abort_if($record->status==='void',422,'لا يمكن الدفع لفاتورة ملغاة.');
   $remaining=round((float)$record->amount-(float)Payment::where('company_id',$c)->where('invoice_id',$record->id)->sum('amount'),3);
   if(round((float)$data['amount'],3)>$remaining)throw ValidationException::withMessages(['amount'=>'المبلغ أعلى من الرصيد المتبقي ('.number_format($remaining,3).').']);
   $account=FinancialAccount::where('company_id',$c)->findOrFail($data['financial_account_id']);
   if(($data['method']==='cash' && $account->type!=='cash') || (in_array($data['method'],['transfer','card'],true) && $account->type!=='bank'))throw ValidationException::withMessages(['financial_account_id'=>'نوع الحساب غير متوافق مع وسيلة الدفع.']);
   $payment=Payment::create([...collect($data)->except('financial_account_id')->all(),'company_id'=>$c,'invoice_id'=>$record->id,'created_by'=>auth()->id()]);
   $lease=$record->lease;
   LedgerEntry::create(['company_id'=>$c,'financial_account_id'=>$account->id,'property_id'=>$lease?->unit?->property_id,'unit_id'=>$lease?->unit_id,'contact_id'=>$record->contact_id,'invoice_id'=>$record->id,'source_payment_id'=>$payment->id,'created_by'=>auth()->id(),'posted_on'=>$data['paid_on'],'direction'=>'in','category'=>$lease?'rent':'general','amount'=>$data['amount'],'bank_reference'=>$data['reference']??null,'description'=>'تحصيل فاتورة '.$record->number]);
   $due=round($remaining-(float)$data['amount'],3);
   $record->update(['status'=>$due<=0.0001?'paid':'partially_paid']);
   return $payment;
  });
  Audit::record('payment','payments',$payment->id,'دفعة مسددة لفاتورة '.$invoice);
  return back()->with('success','تم تسجيل الدفعة وتحديث حالة الفاتورة.');
 }
}
