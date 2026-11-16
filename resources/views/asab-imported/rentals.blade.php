@extends('layouts.app')
@section('title', 'السجل التاريخي للإيجارات')
@section('content')
<style>
.asab-history{--ah:#0d2941;--ac:#10b99f;color:#193247}.asab-history .ah-head{display:flex;justify-content:space-between;align-items:flex-start;flex-wrap:wrap;gap:16px;margin-bottom:24px}.asab-history h1{font-size:clamp(24px,3vw,34px);font-weight:800;margin:4px 0 8px}.asab-history p{color:#61758a;margin:0;line-height:1.9}.asab-history .ah-eyebrow{font-size:12px;font-weight:800;letter-spacing:1px;color:#099e89}.asab-history .ah-summary{display:flex;gap:12px;flex-wrap:wrap}.asab-history .ah-mini{background:white;border:1px solid #e2eaf0;border-radius:16px;padding:15px 20px;min-width:125px}.asab-history .ah-mini strong{display:block;font-size:23px;color:#0d2941}.asab-history .ah-mini small{color:#718297}.asab-history .ah-warning{background:#fff7e4;border:1px solid #f0da9d;padding:13px 17px;border-radius:14px;color:#795817;margin-bottom:18px;font-size:13px;line-height:1.9}.asab-history .ah-panel{background:#fff;border:1px solid #e4eaf0;border-radius:20px;padding:19px;box-shadow:0 8px 30px rgba(6,35,59,.035)}.asab-history .ah-filters{display:flex;gap:12px;flex-wrap:wrap;margin-bottom:18px;align-items:end}.asab-history .ah-filters label{display:block;color:#536578;font-size:12px;margin-bottom:5px}.asab-history .ah-filters select,.asab-history .ah-filters input{min-height:43px;min-width:190px;background:#f9fbfd;border:1px solid #dce7ed;border-radius:11px;padding:8px 12px;max-width:100%;color:#123}.asab-history .ah-btn{min-height:43px;padding:9px 19px;background:#0d2941;color:white;border:0;border-radius:11px;font-size:13px;font-weight:700;cursor:pointer}.asab-history .ah-link{display:inline-flex;align-items:center;gap:7px;border:1px solid #d7e5e8;background:#f7fcfa;border-radius:11px;padding:8px 14px;color:#117b71;font-weight:700;font-size:12px;text-decoration:none}.asab-history .ah-table-wrap{overflow:auto;width:100%}.asab-history table{min-width:1020px;width:100%;border-collapse:collapse;font-size:12px}.asab-history th{text-align:right;font-weight:750;color:#3c6272;background:#edf5f6;white-space:nowrap}.asab-history th,.asab-history td{border-bottom:1px solid #eef1f3;padding:12px 11px;vertical-align:top}.asab-history td{color:#24394b}.asab-history td small{color:#78909c}.asab-history .ah-amount{font-weight:750;direction:ltr;white-space:nowrap}.asab-history .ah-tag{display:inline-block;padding:3px 8px;border-radius:50px;background:#fff0d7;color:#986b22;font-size:11px}.asab-history .ah-footer{margin-top:17px}.asab-history .ah-empty{text-align:center;padding:45px;color:#687c8b}@media(max-width:700px){.asab-history .ah-panel{padding:12px}.asab-history .ah-filters>*{flex:1 1 100%}.asab-history .ah-filters select,.asab-history .ah-filters input{width:100%}}
</style>
<div class="asab-history">
 <div class="ah-head">
  <div><span class="ah-eyebrow">ASAB SOFT / PROPERTY HISTORY</span><h1>السجل التاريخي للإيجارات</h1><p>فترات الإيجار والمبالغ والسندات السابقة، مرتبة حسب العقار والوحدة والمستأجر.</p></div>
  <div class="ah-summary"><div class="ah-mini"><strong>{{ number_format($total) }}</strong><small>سجل تاريخي مطابق للتصفية</small></div><a class="ah-link" href="{{ route('modules.index','properties') }}">عرض العقارات ←</a></div>
 </div>
 <div class="ah-warning">يعرض السجل الفترات والمبالغ التاريخية. ظهور سند أو مبلغ هنا لا يعني ترحيله إلى الحسابات أو اعتماد الإشغال الحالي.</div>
 <div class="ah-panel">
  <form method="GET" action="{{ route('asab.history.rentals') }}" class="ah-filters">
   <div><label for="ah_property">العقار</label><select id="ah_property" name="property"><option value="">جميع العقارات</option>
   @foreach($options as $id => $name)
   <option value="{{ $id }}" @selected(request('property') == $id)>{{ $name }}</option>
   @endforeach
   </select></div>
   <div><label for="ah_q">الاسم أو رمز الوحدة أو السند</label><input id="ah_q" name="q" maxlength="100" value="{{ request('q') }}" placeholder="بحث في السجلات..."></div>
   <div><label for="rent_min">قيمة من</label><input id="rent_min" type="number" name="min_amount" min="0" step="0.001" value="{{ request('min_amount') }}"></div>
   <div><label for="rent_max">قيمة إلى</label><input id="rent_max" type="number" name="max_amount" min="0" step="0.001" value="{{ request('max_amount') }}"></div>
   <div><label for="rent_kind">نوع السجل</label><select id="rent_kind" name="kind"><option value="">الكل</option>
   @foreach($kinds as $key=>$label)<option value="{{ $key }}" @selected(request('kind')===$key)>{{ $label }}</option>@endforeach
   </select></div>
   <button class="ah-btn" type="submit">بحث وتصفية</button><a class="ah-link" href="{{ route('asab.history.rentals') }}">إعادة ضبط</a>
  </form>
  <div class="ah-table-wrap"><table><thead><tr><th>العقار / الوحدة</th><th>المستأجر</th><th>الفترة الأصلية</th><th>المبلغ المسجل</th><th>السند</th><th>رقم السجل</th><th>الحالة</th></tr></thead><tbody>
  @forelse($items as $record)
   <tr><td><b>{{ $record->property?->name }}</b><div><small>{{ $record->unit?->code }}</small></div></td><td>{{ $record->contact?->name ?: ($record->tenant_label ?: 'الاسم غير محدد') }} @if($record->tenant_origin_row && (int)$record->tenant_origin_row!==(int)$record->source_row)<small class="ops-history-note">تابع لسجل المستأجر السابق في نفس الوحدة</small>@endif</td>
   <td><div dir="ltr">{{ $record->date_from_source ?: '—' }}</div><div dir="ltr">{{ $record->date_to_source ?: '—' }}</div></td>
   <td class="ah-amount">{{ $record->source_amount !== null ? \App\Support\Workspace::company()->money($record->source_amount) : '—' }}</td>
   <td>{{ $record->reference ?: '—' }}</td><td><small>#{{ $record->id }}</small></td><td><span class="ops-kind {{ $record->entry_kind==='payment_candidate'?'ops-kind-review':'' }}">{{ $kinds[$record->entry_kind] ?? 'سجل سابق' }}</span></td></tr>
  @empty
   <tr><td colspan="7" class="ah-empty">لا توجد سجلات تاريخية مطابقة لهذه التصفية.</td></tr>
  @endforelse
  </tbody></table></div><div class="ah-footer">{{ $items->links() }}</div>
 </div>
</div>
@endsection
