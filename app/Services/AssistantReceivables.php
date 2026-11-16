<?php
namespace App\Services;

use App\Models\{Company,Contact,Invoice,Payment};

final class AssistantReceivables
{
    private const FINANCE_ROLES=['company_admin','manager','accountant'];

    public static function isPersonal(string $text):bool
    {
        return preg_match('/(?:كم (?:علي|باقي علي)|هل (?:علي|عندي) |ايجاري|دفعاتي|فواتيري|ذمتي|عقدي|علي ?دفعات|علي ?مستحقات|علي ?دفعه|انا ?كمستاجر|ما علي|لا يوجد علي|مدفوعاتي)/u',$text)===1;
    }

    public function reply(string $normalized,Company $company,string $role):array
    {
        if(self::isPersonal($normalized)){
            return ['personal_balance','حساب الدخول الحالي حساب تشغيل وليس حساب مستأجر مرتبطاً بعقد. لذلك لا أستطيع نسبة أي دفعة أو رصيد إليك، وصفر على مستوى الشركة لا يعني أن ذمتك مسددة. اطلب كشفاً لفاتورتك من إدارة العقار. إذا كنت تدير الحسابات، اسألني مثلاً «مستحقات المستأجر: الاسم الكامل».'];
        }
        if(!in_array($role,self::FINANCE_ROLES,true)){
            return ['finance_access','عرض المبالغ والتفاصيل المالية في المساعد متاح للأدوار المخوّلة. يمكنك طلب كشف من المدير أو المحاسب.'];
        }
        $contact=null;
        if(preg_match('/(?:مستاجر|عميل)\s+رقم\s+(\d{1,12})\b/u',$normalized,$m)){
            $contact=Contact::where('company_id',$company->id)->where('type','tenant')->find((int)$m[1]);
            if(!$contact)return ['unknown_tenant','رقم المستأجر غير موجود في مساحة العمل الحالية.'];
        }elseif(preg_match('/(?:^|\s)(?:للمستاجر|المستاجر|لعميل|العميل|للمستاجره|المستاجره)\s+(.+)$/u',$normalized,$m)){
            $typed=trim(preg_replace('/\s+(?:هذا الشهر|هالشهر|الشهر الحالي|الشهر الماضي|الشهر السابق|اليوم)$/u','',$m[1]));
            if(mb_strlen($typed)<3)return ['ask_tenant','اكتب الاسم الكامل للمستأجر، مثلاً «مستحقات المستأجر: الاسم الكامل».'];
            $matches=[];
            foreach(Contact::where('company_id',$company->id)->where('type','tenant')->select('id','name')->cursor() as $row){
                if(LocalAssistant::normalize($row->name)!==$typed)continue;
                $matches[]=$row;
                if(count($matches)>1)return ['ambiguous_tenant','وجدت أكثر من مستأجر بالاسم نفسه، لذلك لن أجمع أرصدتهم. استخدم رقم المستأجر، مثلاً «مستحقات مستأجر رقم 42».'];
            }
            $contact=$matches[0]??null;
            if(!$contact)return ['unknown_tenant','لم أجد مستأجراً بهذا الاسم الكامل في مساحة العمل. تحقق من الاسم أو استخدم رقم المستأجر.'];
        }elseif(preg_match('/\b(?:المستاجر|مستاجر|المستاجرين|عميل|العميل)\b/u',$normalized)){
            return ['ask_tenant','من المستأجر المقصود؟ اكتب «مستحقات المستأجر: الاسم الكامل» أو «مستحقات مستأجر رقم 42».'];
        }
        $money=fn($amount)=>$company->money($amount);
        $base=Invoice::where('company_id',$company->id)->where('status','!=','void');
        if($contact)$base->where('contact_id',$contact->id);
        $scope=$contact?'المستأجر '.$contact->name.' (سجل #'.$contact->id.')':'مساحة العمل الحالية';
        if(preg_match('/(?:\b(?:ال)?(?:دفعات|مدفوعات|تحصيل|قبض)\b|دفع هذا الشهر)/u',$normalized) &&
           !preg_match('/(?:غير مسدد|غير مدفوع|باقي|رصيد|مستحق|متاخر|عليه)/u',$normalized)){
            $payments=Payment::where('company_id',$company->id)->whereIn('invoice_id',(clone $base)->select('id'));
            $period='كل التواريخ المسجلة';
            if(preg_match('/(?:هذا الشهر|هالشهر|الشهر الحالي|الشهر الماضي|الشهر السابق)/u',$normalized)){
                $month=now();
                if(str_contains($normalized,'الماضي')||str_contains($normalized,'السابق'))$month->subMonthNoOverflow();
                $period=$month->format('Y-m');
                $payments->whereBetween('paid_on',[$month->copy()->startOfMonth()->toDateString(),$month->copy()->endOfMonth()->toDateString()]);
            }elseif(str_contains($normalized,'اليوم')){
                $period=today()->toDateString();$payments->where('paid_on',$period);
            }
            $count=(clone $payments)->count();$total=(clone $payments)->sum('amount');
            $list=$payments->with('invoice:id,number')->latest('paid_on')->latest('id')->limit(5)->get()
                ->map(fn($p)=>'• '.$p->paid_on.' · '.$money($p->amount).' · فاتورة '.$p->invoice?->number)->implode("\n");
            return ['posted_payments',"الدفعات المعتمدة — {$scope}، $period: $count دفعة بإجمالي ".$money($total).".\n".($list?:'لا توجد دفعات معتمدة في النطاق المحدد.')."\nالسجلات التاريخية قيد المراجعة لا تدخل في الإجمالي."];
        }
        $invoices=(clone $base)->whereIn('status',['unpaid','partially_paid']);
        if(preg_match('/متاخر(?:ات|ه)?|تجاوزت|متاخره/u',$normalized))$invoices->whereDate('due_on','<',today()->toDateString());
        $period='';
        if(preg_match('/(?:هذا الشهر|هالشهر|الشهر الحالي|الشهر الماضي|الشهر السابق)/u',$normalized)){
            $month=now();
            if(str_contains($normalized,'الماضي')||str_contains($normalized,'السابق'))$month->subMonthNoOverflow();
            $period=' · استحقاقات '.$month->format('Y-m');
            $invoices->whereBetween('due_on',[$month->copy()->startOfMonth()->toDateString(),$month->copy()->endOfMonth()->toDateString()]);
        }
        $positive=(clone $invoices)->orderBy('due_on')->withSum(['payments as workspace_paid'=>fn($q)=>$q->where('company_id',$company->id)],'amount')->cursor();
        $total=0;$count=0;$sample=[];
        foreach($positive as $invoice){
            $balance=max(0,round((float)$invoice->amount-(float)$invoice->workspace_paid,3));
            if($balance<=0)continue;
            $total+=$balance;$count++;
            if(count($sample)<5)$sample[]='• '.$invoice->number.' · الباقي '.$money($balance).' · الاستحقاق '.$invoice->due_on;
        }
        return ['unpaid_invoices',"الفواتير ذات الرصيد المتبقي — $scope$period: {$count}، والإجمالي ".$money($total).".\n".
            ($sample?implode("\n",$sample):'لا يظهر رصيد متبقٍ في الفواتير المسجلة ضمن هذا النطاق.')."\nالمصدر هو الفواتير المعتمدة في المنصة. السجل التاريخي غير المعتمد ليس إبراء ذمة."];
    }
}
