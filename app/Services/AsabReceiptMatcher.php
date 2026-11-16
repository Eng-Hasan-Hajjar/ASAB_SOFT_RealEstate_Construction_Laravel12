<?php
namespace App\Services;

use App\Models\{AsabFinancialHistory,Company,Invoice,Lease};
use Illuminate\Support\Facades\{Crypt,DB};

final class AsabReceiptMatcher
{
    // Recommendations only. A payer may not be the tenant, and a bank may have repeated rows.
    public function execute(Company $company,bool $apply): array
    {
        $rows=AsabFinancialHistory::where('company_id',$company->id)->where('flow','in')->orderBy('id')->get();
        $counts=['receipts'=>0,'posted'=>0,'missing_location'=>0,'missing_date'=>0,'possible_duplicate'=>0,'needs_lease'=>0,'needs_invoice'=>0,'ready_for_review'=>0];
        $prepared=[];
        $signatures=[];
        foreach($rows as $row){
            $key=$this->fingerprint($row);
            if($key!==null)$signatures[$key]=($signatures[$key]??0)+1;
        }
        foreach($rows as $row){
            $counts['receipts']++;
            $lease=null;$invoice=null;
            $key=$this->fingerprint($row);
            if($row->review_status==='verified_posted')$state='posted';
            elseif($key!==null && $signatures[$key]>1)$state='possible_duplicate';
            elseif(!$row->property_id || !$row->unit_id)$state='missing_location';
            elseif(!$row->event_on)$state='missing_date';
            else{
                $date=$row->event_on->toDateString();
                $candidates=Lease::where('company_id',$company->id)->where('unit_id',$row->unit_id)->whereIn('status',['active','ended'])
                    ->where('starts_on','<=',$date)->where('ends_on','>=',$date)
                    ->whereHas('unit',fn($q)=>$q->where('company_id',$company->id)->where('property_id',$row->property_id))
                    ->with('contact')->limit(2)->get();
                $candidate=$candidates->count()===1?$candidates->first():null;
                $payer=$this->normalized($row->party_name);
                $partyMatches=$candidate && $candidate->contact && (int)$candidate->contact->company_id===(int)$company->id
                    && $payer!=='' && $payer===$this->normalized($candidate->contact->name);
                $refMatches=$candidate && $row->voucher_number && trim((string)$candidate->legacy_receipt_number)===trim($row->voucher_number);
                if(!$candidate || (!$partyMatches && !$refMatches))$state='needs_lease';
                else{
                    $lease=$candidate->id;
                    $matches=Invoice::where('company_id',$company->id)->where('lease_id',$lease)->where('contact_id',$candidate->contact_id)
                        ->whereIn('status',['unpaid','partially_paid'])->where('period_start','<=',$date)->where('period_end','>=',$date)
                        ->where('amount','>=',$row->source_amount)->withSum('payments','amount')->limit(2)->get();
                    $target=$matches->count()===1?$matches->first():null;
                    if(!$target || round((float)$target->amount-(float)$target->payments_sum_amount,3)<(float)$row->source_amount)$state='needs_invoice';
                    else{$invoice=$target->id;$state='ready_for_review';}
                }
            }
            $counts[$state]++;
            $prepared[]=['company_id'=>$company->id,'history_id'=>$row->id,'lease_id'=>$lease,'invoice_id'=>$invoice,'state'=>$state,'created_at'=>now(),'updated_at'=>now()];
        }
        if($apply)DB::transaction(function()use($prepared){
            foreach(array_chunk($prepared,100) as $chunk)DB::table('asab_receipt_matches')->upsert($chunk,['company_id','history_id'],['lease_id','invoice_id','state','updated_at']);
        },2);
        return $counts;
    }

    private function normalized(?string $value): string
    {
        return preg_replace('/[^\p{L}\p{N}]/u','',mb_strtolower(trim($value??'')))??'';
    }

    private function fingerprint(AsabFinancialHistory $row): ?string
    {
        if($row->channel==='cash' && $row->voucher_number)return 'cash|'.trim($row->voucher_number);
        if($row->channel!=='bank' || !$row->event_on || !$row->party_name)return null;
        try {
            $cells=json_decode(Crypt::decryptString($row->encrypted_source),true,512,JSON_THROW_ON_ERROR);
            $time=trim((string)($cells['H']??''));
        }catch(\Throwable){return null;}
        if($time==='')return null;
        return 'bank|'.implode('|',[$row->event_on->toDateString(),$time,$this->normalized($row->party_name),$this->normalized($row->bank_name),$this->normalized($row->property_label),$this->normalized($row->unit_label),$row->source_amount]);
    }
}
