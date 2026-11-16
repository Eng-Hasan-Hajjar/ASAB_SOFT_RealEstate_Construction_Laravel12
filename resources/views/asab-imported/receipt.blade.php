@extends('layouts.app')
@section('title','مطابقة دفعة سابقة')
@section('content')
<div class="page-heading"><div><span class="eyebrow">ASAB · RECEIPT REVIEW</span><h1>مطابقة دفعة سابقة</h1><p>اعتماد التحصيل يربط الدفعة بالفاتورة والعقد ويحدّث الرصيد تلقائيًا.</p></div><a class="btn btn-outline" href="{{ route('asab.history.finance',['flow'=>'in']) }}">العودة للدفعات</a></div>
<div class="content-grid">
 <section class="panel detail-panel"><h3>بيانات الدفعة السابقة</h3><dl class="details-list">
  <div><dt>المبلغ</dt><dd>{{ \App\Support\Workspace::company()->money($record->source_amount) }}</dd></div>
  <div><dt>القناة</dt><dd>{{ $record->channel==='cash'?'نقدي':'بنكي' }}</dd></div>
  <div><dt>العقار / الوحدة</dt><dd>{{ $record->property_label ?: 'غير محدد' }} / {{ $record->unit_label ?: 'غير محددة' }}</dd></div>
  <div><dt>الطرف / السند</dt><dd>{{ $record->party_name ?: '—' }} / {{ $record->voucher_number ?: '—' }}</dd></div>
  <div><dt>التاريخ كما ورد</dt><dd>{{ $record->source_date_label ?: '—' }}</dd></div>
  <div><dt>رقم السجل</dt><dd>#{{ $record->id }}</dd></div>
  <div><dt>مرجع التدقيق</dt><dd>{{ $record->source_file }} / {{ $record->source_sheet }} / صف {{ $record->source_row }} / عمود {{ $record->source_column }}</dd></div>
 </dl></section>
 <section class="panel detail-panel"><h3>ترحيل الدفعة</h3>
 @if($posting)
 <p>تم ترحيل هذه الدفعة. مرجع المستند {{ $posting->evidence_reference }}، واعتمدها {{ $posting->approved_by }}.</p>
 @elseif($accounts->isEmpty())
 <p>أنشئ حسابًا ماليًا نشطًا يطابق قناة الدفع من الصندوق والبنوك، ثم ارجع إلى هذه الدفعة.</p>
 @else
 @if($invoices->isEmpty())<p>لا توجد فاتورة مقترحة في هذا العقار أو الوحدة. تحقّق من العقد وأصدر الفاتورة أولًا، أو أدخل رقم سجل الفاتورة المتحقّق منها.</p>@endif
 <form method="post" action="{{ route('asab.receipts.post',$record) }}" class="form-grid">@csrf
 <label class="filter-control"><span>معرّف سجل الفاتورة من الرابط أو القائمة</span>
 <input type="number" min="1" name="invoice_id" list="candidate-invoices" required value="{{ old('invoice_id',$match?->invoice_id) }}">
 <datalist id="candidate-invoices">@foreach($invoices as $invoice)<option value="{{ $invoice->id }}">{{ $invoice->number }} · {{ $invoice->contact?->name }} · {{ $invoice->lease?->unit?->property?->name }} / {{ $invoice->lease?->unit?->code }}</option>@endforeach</datalist></label>
 <label class="filter-control"><span>الصندوق أو البنك</span><select name="financial_account_id" required><option value="">اختر الحساب الفعلي</option>
 @foreach($accounts as $account)<option value="{{ $account->id }}" @selected(old('financial_account_id')==$account->id)>{{ $account->name }}</option>@endforeach</select></label>
 <label class="filter-control"><span>تاريخ التحصيل المؤكد</span><input type="date" required name="posted_on" value="{{ old('posted_on',$record->event_on?->toDateString()) }}"></label>
 <label class="filter-control"><span>رقم المستند أو المرجع البنكي الفريد</span><input required name="evidence_reference" maxlength="120" value="{{ old('evidence_reference') }}"></label>
 @if($match?->state==='possible_duplicate')<label class="wide"><input type="checkbox" name="duplicate_reviewed" value="1" required> راجعت الصف المكرر المحتمل وتأكدت من أنه تحصيل مستقل.</label>@endif
 <label class="wide"><input type="checkbox" name="confirmed" value="1" required> طابقت كشف البنك أو السند، الفاتورة والمستأجر، وتأكدت أن الدفعة لم تُسجل سابقًا.</label>
 <button type="submit" class="btn btn-primary">اعتماد وترحيل الدفعة</button>
 </form>
 @endif
 </section>
</div>
@endsection
