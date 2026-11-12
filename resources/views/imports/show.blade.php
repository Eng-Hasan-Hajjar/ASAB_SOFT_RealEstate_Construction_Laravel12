@extends('layouts.app')
@section('title','مراجعة دفعة Excel')
@section('content')
<div class="page-heading"><div><span class="eyebrow">IMPORT PREVIEW</span><h1>{{ $record->source_filename }}</h1><p>هذه معاينة للصفوف المصدرية، وليست عقوداً أو دفعات مُرحّلة إلى الحسابات.</p></div><a href="{{ route('imports.index') }}" class="btn btn-outline">← جميع الدفعات</a></div>
<div class="stats-grid reports-stats"><div class="stat-card"><span>نوع المصدر</span><strong class="small-value">{{ $record->profile }}</strong></div><div class="stat-card"><span>عدد الصفوف</span><strong>{{ $record->rows_total }}</strong></div><div class="stat-card"><span>صفوف تحتاج تدقيقاً</span><strong>{{ $record->rows_flagged }}</strong></div><div class="stat-card"><span>الحالة</span><strong class="small-value">بانتظار مراجعة</strong></div></div>
<section class="panel"><div class="panel-head"><h3>معاينة محتويات الأوراق</h3></div><div class="import-cards">
@forelse($rows as $row)<div class="import-row {{ $row->state==='flagged'?'flagged':'' }}"><div class="import-row-head"><b>{{ $row->source_sheet }} · الصف {{ $row->row_number }}</b><span>{{ $row->state==='flagged'?'⚠ يحتاج تدقيقاً':'بيانات مستخرجة' }}</span></div><div class="import-cells">@foreach(array_slice($row->payload['cells']??[],0,12) as $i=>$cell)@if(trim((string)$cell)!=='')<span><small>عمود {{ $i+1 }}</small><b>{{ \Illuminate\Support\Str::limit($cell,100) }}</b></span>@endif @endforeach</div></div>
@empty<div class="empty-small">لا توجد صفوف.</div>@endforelse
</div>{{ $rows->links() }}</section>
<form class="delete-import" method="POST" action="{{ route('imports.delete',$record) }}" onsubmit="return confirm('حذف دفعة المراجعة وكل صفوفها نهائياً؟')">@csrf @method('DELETE')<button class="btn btn-outline" type="submit">حذف دفعة المراجعة</button></form>
@endsection
