@extends('layouts.app')
@section('title','إصدار فاتورة')
@section('content')
<div class="page-heading"><div><span class="eyebrow">CREATE INVOICE</span><h1>فاتورة جديدة</h1><p>فاتورة داخلية قابلة للتحصيل ولا تُعد فاتورة ضريبية إلكترونية معتمدة.</p></div><a href="{{ route('invoices.index') }}" class="btn btn-outline">رجوع</a></div>
<form class="panel edit-panel" action="{{ route('invoices.store') }}" method="POST">@csrf<div class="panel-head"><h3>بيانات الفاتورة</h3></div><div class="form-grid">
<div class="form-field"><label>العميل / المستأجر *</label><select name="contact_id" required><option value="">اختر العميل</option>@foreach($contacts as $c)<option value="{{ $c->id }}" @selected(old('contact_id')==$c->id)>{{ $c->name }}</option>@endforeach</select></div>
<div class="form-field"><label>العقد المرتبط (اختياري)</label><select name="lease_id"><option value="">فاتورة مستقلة</option>@foreach($leases as $l)<option value="{{ $l->id }}" @selected(old('lease_id')==$l->id)>{{ $l->contract_number }}</option>@endforeach</select></div>
<div class="form-field"><label>المبلغ *</label><input type="number" step="0.001" min="0.001" name="amount" value="{{ old('amount') }}" required></div>
<div class="form-field"><label>تاريخ الإصدار *</label><input type="date" name="issued_on" value="{{ old('issued_on',today()->toDateString()) }}" required></div>
<div class="form-field"><label>تاريخ الاستحقاق *</label><input type="date" name="due_on" value="{{ old('due_on',today()->addMonth()->toDateString()) }}" required></div>
<div class="form-field wide"><label>البيان</label><textarea name="description" rows="3">{{ old('description') }}</textarea></div></div><div class="form-actions"><button class="btn btn-primary">حفظ وإصدار الفاتورة</button></div></form>
@endsection
