@extends('layouts.app')
@section('title',$def['title'])
@section('content')
<div class="page-heading"><div><span class="eyebrow">ASAB OPERATIONS / {{ strtoupper($module) }}</span><h1>{{ $def['title'] }}</h1><p>{{ $def['description'] }} · عدد السجلات {{ $items->total() }}</p></div>
@if(\App\Support\ModuleRegistry::canWrite(\App\Support\Workspace::role(),$module))<a class="btn btn-primary" href="{{ route('modules.create',$module) }}">+ إضافة {{ $def['singular'] }}</a>@endif</div>
<div class="panel table-panel"><div class="table-toolbar"><form method="GET" action="{{ route('modules.index',$module) }}" class="search-form"><input name="q" type="search" value="{{ $search }}" placeholder="ابحث في السجلات..." aria-label="البحث"><button type="submit">بحث</button></form><span class="soft-label">{{ $items->total() }} نتيجة</span></div>
<div class="table-scroll"><table class="data-table"><thead><tr><th>الرقم</th>@foreach($def['columns'] as $column)<th>{{ $def['fields'][$column]['label'] }}</th>@endforeach<th>الإجراءات</th></tr></thead><tbody>
@forelse($items as $item)
<tr><td class="id-cell">#{{ $item->id }}</td>
 @foreach($def['columns'] as $column)
 @php($field=$def['fields'][$column])
 <td>
 @if($field['type']==='relation')
  {{ $displayOptions[$column][$item->{$column}]??'—' }}
 @elseif($field['type']==='select')
  <span class="status-pill">{{ $field['options'][(string)$item->{$column}]??$item->{$column}??'—' }}</span>
 @elseif($field['type']==='money')
  <span class="money-text" dir="ltr">{{ \App\Support\Workspace::company()->money($item->{$column}) }}</span>
 @elseif($field['type']==='progress')<span class="progress-mini">{{ $item->{$column} }}%</span>
 @else {{ \Illuminate\Support\Str::limit((string)$item->{$column},65)?:'—' }}@endif
 </td>
 @endforeach
 <td class="actions-cell">
 @if(in_array($module,['projects','properties'],true))<a class="table-action" href="{{ route('modules.show',[$module,$item->id]) }}">تفاصيل</a>@endif
 @if(\App\Support\ModuleRegistry::canWrite(\App\Support\Workspace::role(),$module))
 <a class="table-action" href="{{ route('modules.edit',[$module,$item->id]) }}">تعديل</a>
 @if(in_array(\App\Support\Workspace::role(),['company_admin','manager'],true))<form method="POST" action="{{ route('modules.destroy',[$module,$item->id]) }}" class="inline-form js-confirm" data-confirm="هل أنت متأكد من حذف السجل؟ قد يتعذر حذف السجلات المرتبطة.">@csrf @method('DELETE')<button type="submit" class="table-action danger-text">حذف</button></form>@endif
 @else <span class="muted">قراءة فقط</span> @endif
 </td>
</tr>
@empty<tr><td colspan="{{ count($def['columns'])+2 }}"><div class="empty-state"><span>⌁</span><strong>لا توجد سجلات حتى الآن</strong><p>ابدأ بإضافة أول {{ $def['singular'] }} أو جرّب كلمات بحث أخرى.</p></div></td></tr>@endforelse
</tbody></table></div>{{ $items->links() }}</div>
@endsection
