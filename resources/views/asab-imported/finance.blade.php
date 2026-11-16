@extends('layouts.app')
@section('title', 'مراجعة التحصيلات السابقة')
@section('content')
<style>
.asab-history{color:#21394d}.asab-history .ah-head{display:flex;justify-content:space-between;align-items:flex-start;gap:16px;flex-wrap:wrap;margin-bottom:18px}.asab-history h1{font-size:clamp(24px,3vw,34px);font-weight:800;margin:6px 0 8px}.asab-history p{color:#66798e;line-height:1.8}.asab-history .ah-eyebrow{font-size:12px;color:#0a9c87;font-weight:800}.asab-history .ah-warning{background:#fff7e5;border:1px solid #f1daac;color:#82621d;border-radius:14px;padding:14px 17px;font-size:13px;line-height:1.9;margin-bottom:18px}.asab-history .ah-card{border:1px solid #e1e9ef;background:white;border-radius:19px;padding:19px;margin-bottom:18px}.asab-history .ah-cards{display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:12px;margin-bottom:17px}.asab-history .ah-kpi{border:1px solid #e2e9ed;background:#fbfdfc;border-radius:16px;padding:16px}.asab-history .ah-kpi strong{display:block;font-size:21px;color:#14324a;direction:ltr;text-align:right}.asab-history .ah-kpi small{font-size:12px;color:#738294}.asab-history .ah-filter{display:flex;flex-wrap:wrap;gap:11px;align-items:end;margin-bottom:17px}.asab-history label{display:block;font-size:12px;color:#52697d;margin-bottom:5px}.asab-history input{border:1px solid #dce6ea;background:#fafdff;padding:10px;min-height:42px;min-width:175px;border-radius:10px}.asab-history select{border:1px solid #dce6ea;background:#fafdff;padding:10px;min-height:42px;min-width:175px;border-radius:10px}.asab-history button,.asab-history .ah-reset{padding:10px 17px;border-radius:10px;font-size:13px;font-weight:700;min-height:42px}.asab-history button{background:#0f3048;color:#fff;border:0;cursor:pointer}.asab-history .ah-reset{background:#f3f9f8;color:#077b70;border:1px solid #d8e8e4;text-decoration:none}.asab-history .ah-table-wrap{overflow:auto}.asab-history table{min-width:1060px;width:100%;border-collapse:collapse;font-size:12px}.asab-history th{text-align:right;white-space:nowrap;background:#eef5f6;color:#466173}.asab-history td,.asab-history th{padding:11px 10px;border-bottom:1px solid #edf0f3;vertical-align:top}.asab-history td small{color:#7a8b97}.asab-history .amount{direction:ltr;text-align:right;font-weight:800;white-space:nowrap}.asab-history .tag{padding:3px 8px;background:#fff1d9;color:#987022;border-radius:99px;font-size:11px;white-space:nowrap}.asab-history .empty{text-align:center;padding:38px;color:#637a88}@media(max-width:650px){.asab-history .ah-card{padding:11px}.asab-history .ah-filter>*{flex:1 1 100%}.asab-history input{border:1px solid #dce6ea;background:#fafdff;padding:10px;min-height:42px;min-width:175px;border-radius:10px}.asab-history select{width:100%}}
</style>
<div class="asab-history">
 <div class="ah-head"><div><span class="ah-eyebrow">ASAB SOFT / HISTORICAL CASHBOOK</span><h1>مراجعة التحصيلات السابقة</h1><p>راجع التحصيلات النقدية والبنكية السابقة قبل اعتمادها في الحسابات.</p></div><div><strong>{{ number_format($total) }}</strong> سجل ضمن التصفية</div></div>
 <div class="ah-warning"><b>مهم:</b> الأرقام في هذه الشاشة مصدرية وتشمل غير المرحّل. لا تؤثر الحركات غير المرحلة على رصيد الصندوق والبنك، ولا على تقارير التحصيل المعتمدة أو الذمم. بعض الكشوف تتضمن مجاميع حسابية غير صحيحة أو معلومات تحتاج مطابقة مع كشف البنك والسندات.</div>
 <div class="ah-cards">
 @foreach($summary as $entry)
 <div class="ah-kpi"><small>{{ match($entry->channel) {'cash'=>'نقدي','bank'=>'بنكي','bank_auxiliary'=>'تفاصيل بنكية إضافية',default=>$entry->channel} }} · {{ match($entry->flow) {'in'=>'تحصيل','out'=>'مصروف','review'=>'مراجعة',default=>$entry->flow} }}</small><strong>{{ number_format((float)$entry->total_amount,2) }} ر.س</strong><small>{{ number_format($entry->count_rows) }} سجل تاريخي</small></div>
 @endforeach
 </div>
 <div class="ah-card">
  <form class="ah-filter" method="GET" action="{{ route('asab.history.finance') }}">
   <div><label for="channel">قناة التحصيل</label><select name="channel" id="channel"><option value="">جميع القنوات</option>
   @foreach(['cash'=>'نقدي','bank'=>'بنكي','bank_auxiliary'=>'قائمة بنكية إضافية'] as $key=>$name)
   <option value="{{ $key }}" @selected(request('channel')===$key)>{{ $name }}</option>
   @endforeach
   </select></div>
   <div><label for="flow">النوع</label><select name="flow" id="flow"><option value="">جميع الحركات</option>
   @foreach(['in'=>'تحصيل','out'=>'مصروف','review'=>'مراجعة'] as $key=>$name)
   <option value="{{ $key }}" @selected(request('flow')===$key)>{{ $name }}</option>
   @endforeach
   </select></div>
   <div><label for="property">العقار</label><select name="property" id="property"><option value="">جميع العقارات</option>
   @foreach($options as $id=>$name)
   <option value="{{ $id }}" @selected(request('property')==$id)>{{ $name }}</option>
   @endforeach
   </select></div>
   <div><label for="history_q">الاسم / السند / الوحدة</label><input id="history_q" name="q" maxlength="120" value="{{ request('q') }}" placeholder="بحث في البيانات الأصلية..."></div>
   <div><label for="history_status">حالة المراجعة</label><select id="history_status" name="status"><option value="">الكل</option><option value="unverified" @selected(request('status')==='unverified')>لم تُعتمد</option><option value="verified_posted" @selected(request('status')==='verified_posted')>تم ترحيلها بعد المطابقة</option></select></div>
   <div><label for="history_from">من تاريخ موثّق</label><input type="date" id="history_from" name="from" value="{{ request('from') }}"></div>
   <div><label for="history_to">إلى تاريخ موثّق</label><input type="date" id="history_to" name="to" value="{{ request('to') }}"></div>
   <div><label for="history_min">من مبلغ</label><input type="number" step="0.001" min="0" id="history_min" name="min_amount" value="{{ request('min_amount') }}"></div>
   <div><label for="history_max">إلى مبلغ</label><input type="number" step="0.001" min="0" id="history_max" name="max_amount" value="{{ request('max_amount') }}"></div>
   <button type="submit">عرض النتائج</button><a class="ah-reset" href="{{ route('asab.history.finance') }}">إزالة الفلاتر</a>
  </form>
  <div class="ah-table-wrap"><table><thead><tr><th>القناة</th><th>النوع</th><th>التاريخ المسجّل</th><th>العقار والوحدة</th><th>المُحوِّل أو البيان</th><th>المبلغ الأصلي</th><th>المرجع</th><th>المراجعة</th><th>إجراء</th></tr></thead><tbody>
  @forelse($items as $item)
  <tr><td>{{ match($item->channel) {'cash'=>'نقدي','bank'=>'بنكي','bank_auxiliary'=>'بنكي إضافي',default=>$item->channel} }}</td><td>{{ match($item->flow) {'in'=>'تحصيل','out'=>'مصروف','review'=>'قيد مراجعة',default=>$item->flow} }}</td>
   <td dir="ltr">{{ $item->source_date_label ?: '—' }}</td><td><b>{{ $item->property?->name ?: ($item->property_label ?: 'غير مربوط') }}</b><br><small>{{ $item->unit_label ?: '—' }}</small></td>
   <td>{{ $item->party_name ?: ($item->memo ?: '—') }}</td><td class="amount">{{ number_format((float)$item->source_amount,2) }} ر.س</td>
   <td><small>{{ $item->voucher_number ?: '#'.$item->id }}</small></td><td><span class="tag">{{ $item->review_status === 'verified_posted' ? 'مرحّل بعد المطابقة' : (['ready_for_review'=>'مطابقة مقترحة','needs_lease'=>'بحاجة إلى عقد موثق','needs_invoice'=>'بحاجة إلى فاتورة','missing_location'=>'العقار أو الوحدة غير محددين','missing_date'=>'تاريخ ناقص','possible_duplicate'=>'تكرار محتمل'][$matchStates[$item->id]??'']??'غير معتمد') }}</span></td>
   <td>@if($item->flow==='in')<a class="table-action" href="{{ route('asab.receipts.show',$item) }}">{{ $item->review_status==='verified_posted'?'عرض الدفعة':'مطابقة الدفعة' }}</a>@endif</td>
  </tr>
  @empty
  <tr><td colspan="9" class="empty">لا توجد حركات سابقة مطابقة.</td></tr>
  @endforelse
  </tbody></table></div><div style="margin-top:15px">{{ $items->links() }}</div>
 </div>
</div>
@endsection
