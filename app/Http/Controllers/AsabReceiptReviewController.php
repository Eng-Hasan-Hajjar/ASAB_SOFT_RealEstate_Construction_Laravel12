<?php
namespace App\Http\Controllers;

use App\Models\{AsabFinancialHistory,FinancialAccount,Invoice};
use App\Services\{AsabFinancePosting,Audit};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use RuntimeException;

final class AsabReceiptReviewController extends CompanyBaseController
{
    public function show(int $receipt)
    {
        $company=$this->companyId();
        $record=AsabFinancialHistory::where('company_id',$company)->where('flow','in')->findOrFail($receipt);
        $match=DB::table('asab_receipt_matches')->where('company_id',$company)->where('history_id',$receipt)->first();
        $posting=DB::table('asab_verified_finance_postings')->where('company_id',$company)->where('source_key',$record->source_key)->first();
        $accounts=FinancialAccount::where('company_id',$company)->where('type',$record->channel)->where('currency',\App\Support\Workspace::company()->currency)->where('is_active',true)->orderBy('name')->get();
        $invoices=Invoice::where('company_id',$company)->whereIn('status',['unpaid','partially_paid'])
            ->whereHas('lease',fn($q)=>$q->where('company_id',$company)->whereIn('status',['active','ended'])
               ->when($record->unit_id,fn($q)=>$q->where('unit_id',$record->unit_id))
               ->when($record->property_id,fn($q)=>$q->whereHas('unit',fn($u)=>$u->where('property_id',$record->property_id))))
            ->with(['contact','lease.unit.property'])->latest('id')->limit(100)->get();
        return view('asab-imported.receipt',compact('record','match','posting','accounts','invoices'));
    }

    public function post(Request $request,int $receipt,AsabFinancePosting $posting)
    {
        $company=$this->companyId();
        $record=AsabFinancialHistory::where('company_id',$company)->where('flow','in')->findOrFail($receipt);
        $data=$request->validate([
            'invoice_id'=>['required','integer','min:1'],'financial_account_id'=>['required','integer','min:1'],
            'posted_on'=>['required','date_format:Y-m-d'],'evidence_reference'=>['required','string','min:3','max:120'],
            'confirmed'=>['accepted'],'duplicate_reviewed'=>['sometimes','accepted'],
        ]);
        $state=DB::table('asab_receipt_matches')->where('company_id',$company)->where('history_id',$receipt)->value('state');
        if($state==='possible_duplicate' && !$request->boolean('duplicate_reviewed'))
            throw ValidationException::withMessages(['duplicate_reviewed'=>'هذا الصف مشتبه بتكراره، راجع كشف المصدر قبل اعتماده.']);
        $data['reviewer']=mb_substr(trim(auth()->user()->name).' #'.auth()->id(),0,120);
        try{$made=$posting->settle($company,$record->source_key,$data,true,auth()->id());}
        catch(RuntimeException $e){throw ValidationException::withMessages(['receipt'=>$e->getMessage()]);}
        if($made)Audit::record('posted','excel_receipts',$receipt,'ترحيل دفعة تاريخية معتمدة');
        return redirect()->route('asab.receipts.show',$receipt)->with('success',$made?'تم حفظ الدفعة وربطها بالفاتورة وإضافة قيد التحصيل.':'هذه الدفعة مرحّلة مسبقًا.');
    }
}
