<?php
namespace App\Support;

/** Interpret each unit sheet independently. Never infer dates or amounts from prior lines. */
final class RentalSheetRows
{
    public static function explain(array $sheet, array $people): iterable
    {
        $rows=$sheet['rows']??[];
        usort($rows,fn(array $a,array $b)=>(int)$a['row']<=>(int)$b['row']);
        $anchor=null;
        $opened=false;
        foreach($rows as $row){
            $n=(int)$row['row'];$cells=$row['cells']??[];
            $name=self::cell($cells,'B');$ref=self::cell($cells,'E');
            $from=self::cell($cells,'F');$to=self::cell($cells,'G');
            $amount=ExcelMoney::parse($cells['H']??null);
            $deposit=ExcelMoney::parse($cells['I']??null);
            $sequence=strtr(self::cell($cells,'A'),self::digits());
            $numbered=preg_match('/^[0-9]{1,5}$/D',$sequence)===1;
            $head=self::text($name);
            $header=in_array($head,['المستاجر','اسم المستاجر','الاسم','اسم العميل','اسم المستفيد'],true)
                && (str_contains(self::text($from),'العقد') || str_contains(self::text($ref),'السند') || self::text(self::cell($cells,'H'))==='المبلغ');
            // Never treat formula totals, headings or explicitly vacant rows as tenants or payments.
            $markers=array_map(fn($key)=>self::text(self::cell($cells,$key)),['B','E','F','G','K','L']);
            $summary=false;$vacancy=false;
            foreach($markers as $marker){
                $summary=$summary||preg_match('/^(?:ال)?(?:مجموع|مجموعه|اجمالي|صافي|رصيد افتتاحي|بدايه سنه جديده|سنه جديده)(?:[\s:：]|$)/u',$marker)===1;
                $vacancy=$vacancy||preg_match('/^(?:شاغره?|فارغه?|خاليه?|اخلاء|انهاء العقد|غير موجره?)(?:[\s:：]|$)/u',$marker)===1;
            }
            $formula=isset($row['formulas']['H']) && preg_match('/\b(?:SUM|SUBTOTAL|AGGREGATE|SUMIF|SUMIFS)\s*\(/i',(string)$row['formulas']['H'])===1;
            $dates=self::dateLike($from)||self::dateLike($to);
            $hasAmount=($amount!==null && (float)$amount>0)||($deposit!==null && (float)$deposit>0);
            $receipt=$ref!=='' && preg_match('/\d/u',$ref)===1 && !str_starts_with($ref,'#');
            if($header){$opened=true;$anchor=null;}
            elseif($summary||$vacancy||$formula){$anchor=null;}
            elseif(isset($people[$n])&&($dates||$hasAmount||$receipt||($opened&&($numbered||self::cell($cells,'C')!==''||self::cell($cells,'D')!=='')))){
                $anchor=$n;$opened=true;
                yield $n=>['row'=>$row,'kind'=>'named','anchor'=>$n];
                continue;
            }elseif($opened && $name!==''){
                // An unrecognized name or note must not silently carry forward the previous identity.
                $anchor=null;
            }elseif($opened && ($dates||$hasAmount||$receipt)){
                $kind=($amount!==null && (float)$amount>0 && $receipt)?'payment_candidate':($hasAmount?'recorded_amount':'period');
                yield $n=>['row'=>$row,'kind'=>$kind,'anchor'=>$anchor];
                continue;
            }
            // Preserve V6 audit identities, but separate non-transaction/placeholder rows from the live view.
            if($numbered||isset($people[$n]))yield $n=>['row'=>$row,'kind'=>'archive_only','anchor'=>null];
        }
    }

    private static function cell(array $cells,string $key): string {return trim((string)($cells[$key]??''));}
    private static function digits(): array {return array_combine(preg_split('//u','٠١٢٣٤٥٦٧٨٩۰۱۲۳۴۵۶۷۸۹',-1,PREG_SPLIT_NO_EMPTY),str_split('01234567890123456789'));}
    private static function text(string $s):string
    {
        $s=trim((string)preg_replace('/[\x{064B}-\x{065F}\x{0640}]/u','',$s));
        return strtr($s,['أ'=>'ا','إ'=>'ا','آ'=>'ا','ى'=>'ي','ة'=>'ه']);
    }
    private static function dateLike(string $s):bool
    {
        $s=strtr($s,self::digits());
        return preg_match('/^(?:[1-8][0-9]{4}|[0-9]{1,4}(?:\s*[-\/.]\s*[0-9]{1,5}){1,2}(?:\s*[هـم])?(?:\s+00:00:00)?)$/uD',$s)===1;
    }
}
