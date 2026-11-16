<?php
namespace App\Services;

use App\Models\{AsabFinancialHistory,Company,FinancialAccount,Invoice,LedgerEntry,Payment};
use DateTimeImmutable;
use Illuminate\Support\Facades\DB;
use RuntimeException;

final class AsabFinancePosting
{
    public function settle(int $company,string $source,array $data,bool $apply,?int $user=null): bool
    {
        return DB::transaction(function()use($company,$source,$data,$apply,$user){
            $workspace=Company::query()->lockForUpdate()->findOrFail($company);
            $h=AsabFinancialHistory::where('company_id',$company)->where('source_key',$source)->lockForUpdate()->first();
            if(!$h)throw new RuntimeException('الحركة المصدرية غير موجودة في الشركة.');
            if(DB::table('asab_verified_finance_postings')->where('company_id',$company)->where('source_key',$source)->exists())return false;
            if(!in_array($h->flow,['in','out'],true)||!in_array($h->channel,['cash','bank'],true))throw new RuntimeException('لا يمكن ترحيل المجاميع أو البنود المساعدة.');
            if(isset($data['source_amount']) && (!is_numeric($data['source_amount']) || round((float)$data['source_amount'],3)!==(float)$h->source_amount))
                throw new RuntimeException('المبلغ لا يطابق Excel.');
            if((isset($data['flow']) && $data['flow']!==$h->flow) || (isset($data['channel']) && $data['channel']!==$h->channel))
                throw new RuntimeException('نوع الحركة لا يطابق المصدر.');
            $account=FinancialAccount::where('company_id',$company)->where('type',$h->channel)->where('currency',$workspace->currency)->where('is_active',true)->find($data['financial_account_id']??null);
            if(!$account)throw new RuntimeException('الحساب المالي غير موجود أو قناته غير مطابقة.');
            $date=(string)($data['posted_on']??'');
            $parsed=DateTimeImmutable::createFromFormat('!Y-m-d',$date);
            if(!$parsed || $parsed->format('Y-m-d')!==$date || ($h->event_on && $h->event_on->toDateString()!==$date))
                throw new RuntimeException('استخدم تاريخ تحصيل ميلادي موثق يطابق التاريخ المقروء من المصدر.');
            $reviewer=trim($data['reviewer']??'');$evidence=trim($data['evidence_reference']??'');
            if(mb_strlen($reviewer)<3||mb_strlen($reviewer)>120||mb_strlen($evidence)<3||mb_strlen($evidence)>120)
                throw new RuntimeException('اسم المعتمد ومرجع المستند مطلوبان قبل الترحيل.');
            $invoice=null;$lease=null;$remaining=null;$payment=null;
            if($h->flow==='in'){
                $invoice=Invoice::where('company_id',$company)->lockForUpdate()->find($data['invoice_id']??null);
                $lease=$invoice?->lease;
                if(!$invoice || !in_array($invoice->status,['unpaid','partially_paid'],true) || !$lease || !in_array($lease->status,['active','ended'],true)
                    || (int)$lease->company_id!==$company || (int)$lease->contact_id!==(int)$invoice->contact_id || (int)$lease->contact?->company_id!==$company
                    || (int)$lease->unit?->company_id!==$company || (int)$lease->unit?->property?->company_id!==$company)
                    throw new RuntimeException('اختر فاتورة غير مسددة لعقد موثق داخل الشركة، وتأكد من تطابق المستأجر.');
                if(($h->unit_id && (int)$h->unit_id!==(int)$lease->unit_id) || ($h->property_id && (int)$h->property_id!==(int)$lease->unit->property_id))
                    throw new RuntimeException('العقار أو الوحدة لا يطابق البيانات المرتبطة بالمصدر.');
                $remaining=round((float)$invoice->amount-(float)Payment::where('company_id',$company)->where('invoice_id',$invoice->id)->sum('amount'),3);
                if((float)$h->source_amount>$remaining)throw new RuntimeException('المبلغ يتجاوز الرصيد المتبقي للفاتورة.');
                $ref=mb_substr($h->voucher_number?:$evidence,0,120);
                if(Payment::where('company_id',$company)->where('reference',$ref)
                    ->when($h->channel==='bank',fn($q)=>$q->where('amount',$h->source_amount)->where('paid_on',$date))->exists())
                    throw new RuntimeException('مرجع الدفعة موجود بالفعل. طابق السجل مع الدفعات القائمة قبل إعادة الترحيل.');
                if(DB::table('asab_verified_finance_postings')->where('company_id',$company)->where('evidence_reference',$evidence)->exists())
                    throw new RuntimeException('مرجع الإثبات استُخدم في ترحيل سابق.');
                if($apply){
                    $payment=Payment::create(['company_id'=>$company,'invoice_id'=>$invoice->id,'amount'=>$h->source_amount,'paid_on'=>$date,
                        'method'=>$h->channel==='cash'?'cash':'transfer','reference'=>mb_substr($ref,0,120),'created_by'=>$user,
                        'notes'=>'تحصيل تاريخي موثق · '.$h->source_key]);
                    $invoice->update(['status'=>round($remaining-(float)$h->source_amount,3)<=0?'paid':'partially_paid']);
                }
                $category='rent';
            }else{
                $category=$data['category']??'';
                if(!empty($data['invoice_id']) || !in_array($category,['maintenance','utilities','salary','supplies','commission','deposit','refund','general','other'],true))
                    throw new RuntimeException('المصروف يحتاج تصنيفًا ولا يرتبط بفاتورة تحصيل.');
            }
            if(!$apply)return true;
            $ledger=LedgerEntry::create([
                'company_id'=>$company,'financial_account_id'=>$account->id,'property_id'=>$lease?->unit?->property_id??$h->property_id,'unit_id'=>$lease?->unit_id??$h->unit_id,
                'contact_id'=>$invoice?->contact_id,'invoice_id'=>$invoice?->id,'source_payment_id'=>$payment?->id,'created_by'=>$user,
                'posted_on'=>$date,'direction'=>$h->flow,'category'=>$category,'amount'=>$h->source_amount,'voucher_number'=>$h->voucher_number,
                'bank_reference'=>$h->channel==='bank'?mb_substr($evidence,0,120):null,'description'=>'ترحيل تاريخي مؤكد · '.$evidence,
            ]);
            DB::table('asab_verified_finance_postings')->insert([
                'company_id'=>$company,'financial_history_id'=>$h->id,'source_key'=>$source,'payment_id'=>$payment?->id,'ledger_entry_id'=>$ledger->id,
                'approved_by'=>$reviewer,'evidence_reference'=>$evidence,'created_at'=>now(),'updated_at'=>now(),
            ]);
            $h->update(['review_status'=>'verified_posted']);
            DB::table('asab_receipt_matches')->where('company_id',$company)->where('history_id',$h->id)->update(['state'=>'posted','invoice_id'=>$invoice?->id,'lease_id'=>$lease?->id,'updated_at'=>now()]);
            return true;
        },2);
    }
}
