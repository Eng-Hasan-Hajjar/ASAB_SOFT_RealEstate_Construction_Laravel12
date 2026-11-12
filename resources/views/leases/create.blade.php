@extends('layouts.app')
@section('title','عقد إيجار جديد')
@section('content')
<div class="page-heading"><div><span class="eyebrow">NEW CONTRACT</span><h1>إنشاء عقد إيجار</h1><p>يحفظ النظام المسودة، ويتحقق من عدم تداخل تواريخ العقود النشطة عند التفعيل.</p></div><a href="{{ route('leases.index') }}" class="btn btn-outline">رجوع</a></div>
<form method="POST" action="{{ route('leases.store') }}" class="panel edit-panel">@csrf
<div class="panel-head"><h3>تفاصيل العقد</h3></div><div class="form-grid">
<div class="form-field"><label>رقم العقد *</label><input name="contract_number" value="{{ old('contract_number','LEASE-'.now()->format('ymd').'-'.strtoupper(substr(bin2hex(random_bytes(3)),0,6))) }}" required></div>
<div class="form-field"><label>الوحدة العقارية *</label><select name="unit_id" required><option value="">— اختر الوحدة —</option>@foreach($units as $u)<option value="{{ $u->id }}" @selected(old('unit_id')==$u->id)>{{ $u->property?->name }} / {{ $u->code }} ({{ $u->status }})</option>@endforeach</select></div>
<div class="form-field"><label>المستأجر *</label><select name="contact_id" required><option value="">— اختر المستأجر —</option>@foreach($contacts as $c)<option value="{{ $c->id }}" @selected(old('contact_id')==$c->id)>{{ $c->name }}</option>@endforeach</select></div>
<div class="form-field"><label>الحالة *</label><select name="status" required><option value="draft" @selected(old('status')==='draft')>مسودة</option><option value="active" @selected(old('status')==='active')>عقد نشط</option></select></div>
<div class="form-field"><label>تاريخ البداية *</label><input type="date" name="starts_on" value="{{ old('starts_on',today()->toDateString()) }}" required></div>
<div class="form-field"><label>تاريخ النهاية *</label><input type="date" name="ends_on" value="{{ old('ends_on') }}" required></div>
<div class="form-field"><label>قيمة الإيجار لكل دورة *</label><input type="number" min="0.001" step="0.001" name="rent_amount" value="{{ old('rent_amount') }}" required></div>
<div class="form-field"><label>مبلغ التأمين</label><input type="number" min="0" step="0.001" name="deposit" value="{{ old('deposit',0) }}"></div>
<div class="form-field"><label>دورية الاستحقاق *</label><select name="billing_cycle"><option value="monthly" @selected(old('billing_cycle')==='monthly')>شهري</option><option value="quarterly" @selected(old('billing_cycle')==='quarterly')>ربع سنوي</option><option value="yearly" @selected(old('billing_cycle')==='yearly')>سنوي</option></select></div>
<div class="form-field wide"><label>ملاحظات</label><textarea rows="3" name="notes">{{ old('notes') }}</textarea></div></div>
<p class="form-tip">تنبيه: هذه سجلات تشغيل داخلية وليست توثيقاً رسمياً لعقود الإيجار وفق متطلبات الجهات التنظيمية.</p>
<div class="form-actions"><button type="submit" class="btn btn-primary">حفظ العقد</button><a href="{{ route('leases.index') }}" class="btn btn-outline">إلغاء</a></div></form>
@endsection
