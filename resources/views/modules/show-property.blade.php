@extends('layouts.app')
@section('title','تفاصيل العقار')
@section('content')
<div class="page-heading"><div><span class="eyebrow">PROPERTY OVERVIEW</span><h1>{{ $item->name }}</h1><p> {{ $item->city }} {{ $item->district?'· '.$item->district:'' }} · {{ $item->category }}</p></div><a href="{{ route('modules.index','properties') }}" class="btn btn-outline">العودة للعقارات</a></div>
<div class="list-actions" style="margin-bottom:20px"><a href="{{ route('modules.create',['module'=>'units','property_id'=>$item->id]) }}" class="btn btn-primary">+ إضافة وحدة لهذا العقار</a><a href="{{ route('listings.edit',$item->id) }}" class="btn btn-outline">إعداد الإعلان والصور والنشر ↗</a></div>
<div class="finance-tiles"><div class="stat-card"><span>إجمالي الوحدات</span><strong>{{ $total }}</strong></div><div class="stat-card"><span>الوحدات المؤجرة</span><strong>{{ $occupied }}</strong></div><div class="stat-card"><span>الوحدات المتاحة</span><strong>{{ $available }}</strong></div></div>
<section class="panel table-panel"><div class="table-toolbar"><h3>وحدات العقار</h3></div><div class="table-scroll"><table class="data-table"><thead><tr><th>الوحدة</th><th>النوع</th><th>المساحة</th><th>الإيجار الشهري</th><th>الحالة</th><th>العقود</th><th>الإجراء</th></tr></thead><tbody>
@forelse($item->units as $u)<tr class="v6-row v6-state-{{ $u->occupancy_status }}"><td>{{ $u->code }}</td><td>{{ $u->type }}</td><td>{{ $u->area?:'—' }}</td><td>{{ \App\Support\Workspace::company()->money($u->monthly_rent) }}</td><td><span class="status-pill">{{ \App\Models\Unit::statusLabels()[$u->occupancy_status] }}</span></td><td>{{ $u->leases_count }}</td><td><a class="table-action view-detail-btn" href="{{ route('modules.preview',['units',$u->id]) }}" data-modal-url="{{ route('modules.preview',['units',$u->id]) }}" data-modal-title="تفاصيل الوحدة {{ $u->code }}">عرض التفاصيل</a></td></tr>@empty<tr><td colspan="7" class="empty-small">لا توجد وحدات بعد.</td></tr>@endforelse
</tbody></table></div></section>
@include('modules.detail-dialog')
@endsection
