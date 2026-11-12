@extends('layouts.app')
@section('title','عقود الإيجار')
@section('content')
<div class="page-heading"><div><span class="eyebrow">RENTAL OPERATIONS</span><h1>عقود الإيجار</h1><p>إدارة العقود النشطة والمسودات والانتهاء، مع منع تعارض فترات إشغال الوحدة.</p></div>@if(in_array(\App\Support\Workspace::role(),['company_admin','manager','agent'],true))<a href="{{ route('leases.create') }}" class="btn btn-primary">+ عقد إيجار جديد</a>@endif</div>
<div class="panel table-panel"><div class="table-scroll"><table class="data-table"><thead><tr><th>رقم العقد</th><th>الوحدة</th><th>المستأجر</th><th>الفترة</th><th>الإيجار</th><th>الحالة</th><th></th></tr></thead><tbody>
@forelse($leases as $l)<tr><td class="cell-strong">{{ $l->contract_number }}</td><td>{{ $l->unit?->property?->name }} / {{ $l->unit?->code }}</td><td>{{ $l->contact?->name }}</td><td class="dir-ltr">{{ $l->starts_on }} — {{ $l->ends_on }}</td><td dir="ltr">{{ \App\Support\Workspace::company()->money($l->rent_amount) }}</td><td><span class="status-pill">{{ ['draft'=>'مسودة','active'=>'نشط','ended'=>'منتهي','cancelled'=>'ملغي'][$l->status]??$l->status }}</span></td><td><a href="{{ route('leases.show',$l) }}" class="table-action">تفاصيل ←</a></td></tr>@empty<tr><td colspan="7"><div class="empty-state">لا توجد عقود مسجلة.</div></td></tr>@endforelse
</tbody></table></div>{{ $leases->links() }}</div>
@endsection
