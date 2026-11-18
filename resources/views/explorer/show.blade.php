@extends('layouts.app')
@section('title','الواجهة التفاعلية')
@section('content')
@php($types=config('modules.units.fields.type.options'))
@php($labels=\App\Models\Unit::statusLabels())
@php($icons=\App\Models\Unit::statusIcons())
@php($quickStatuses=['available','occupied'])
<section class="v6-hero">
 <div><a href="{{ route('explorer.index') }}">الواجهة التفاعلية ← جميع العقارات</a><h1>{{ $item->name }}</h1><p>{{ $item->city }} {{ $item->district ? '· '.$item->district : '' }} · {{ $units->total() }} من إجمالي {{ $totalUnits }} وحدة</p>
 <div class="v6-tags"><a class="btn btn-outline view-detail-btn" href="{{ route('modules.preview',['properties',$item->id]) }}" data-modal-url="{{ route('modules.preview',['properties',$item->id]) }}" data-modal-title="{{ $item->name }}">تفاصيل العقار</a>
 @if(\App\Support\ModuleRegistry::canWrite(\App\Support\Workspace::role(),'units'))<a class="btn btn-outline" href="{{ route('modules.create',['module'=>'units','property_id'=>$item->id]) }}">+ إضافة وحدة</a>@endif</div>
 </div><div class="v6-hero-icon">@if($cover)<img src="{{ route('explorer.image',$cover) }}" alt="">@else @include('explorer.icon',['icon'=>'building']) @endif</div>
</section>

<div class="panel v6-summary" role="group" aria-label="ملخص حالة الوحدات">
 <div class="v6-summary-chips">
 @foreach($labels as $key=>$label)
  <a class="v6-summary-chip v6-state-{{ $key }}" href="{{ request()->fullUrlWithQuery(['status'=>$key,'page'=>null]) }}" aria-current="{{ request('status')===$key?'true':'false' }}">
   @include('explorer.icon',['icon'=>$icons[$key]])
   <strong>{{ $statusCounts[$key] ?? 0 }}</strong><span>{{ $label }}</span>
  </a>
 @endforeach
 </div>
 <p class="v6-helper">الألوان والأيقونات والشارة النصية تشير جميعها إلى نفس الحالة المحسوبة من العقود والحجوزات الفعلية في النظام، وليس من أي تخمين. اضغط أي حالة لتصفية الشبكة بها.</p>
</div>

<form class="panel v6-searchbar" method="get" action="{{ route('explorer.show',$item) }}">
 <label class="filter-control"><span>رمز الوحدة أو رقمها</span><input name="q" type="search" value="{{ request('q') }}" maxlength="120" placeholder="مثال: A101 أو ١٠١"></label>
 <label class="filter-control"><span>النوع</span><select name="type"><option value="">الكل</option>@foreach($types as $key=>$label)<option value="{{ $key }}" @selected(request('type')===$key)>{{ $label }}</option>@endforeach</select></label>
 <label class="filter-control"><span>الطابق</span><select name="floor"><option value="">الكل</option>@foreach($floorOptions as $f)<option value="{{ $f===null?'none':$f }}" @selected(request('floor')===($f===null?'none':(string)$f))>{{ \App\Models\Unit::floorLabel($f) }}</option>@endforeach</select></label>
 <label class="filter-control"><span>الإشغال</span><select name="status"><option value="">الكل</option>@foreach($labels as $key=>$label)<option value="{{ $key }}" @selected(request('status')===$key)>{{ $label }}</option>@endforeach</select></label>
 <button class="btn btn-primary">عرض</button><a class="btn btn-outline" href="{{ route('explorer.show',$item) }}">إعادة الضبط</a>
</form>

<div class="v6-quickfilters" role="group" aria-label="فلاتر سريعة">
 <a class="v6-chip" href="{{ request()->fullUrlWithQuery(['status'=>null,'page'=>null]) }}" aria-current="{{ request('status')?'false':'true' }}">الكل</a>
 @foreach($quickStatuses as $key)
 <a class="v6-chip v6-state-{{ $key }}" href="{{ request()->fullUrlWithQuery(['status'=>$key,'page'=>null]) }}" aria-current="{{ request('status')===$key?'true':'false' }}">@include('explorer.icon',['icon'=>$icons[$key]]) {{ $labels[$key] }}</a>
 @endforeach
</div>

<div class="v6-legend" aria-hidden="true">
 @foreach($labels as $key=>$label)
 <span class="v6-legend-item v6-state-{{ $key }}">@include('explorer.icon',['icon'=>$icons[$key]]) {{ $label }}</span>
 @endforeach
</div>

<div class="v6-building">
@forelse($floorGroups as $floor=>$floorUnits)
<section class="v6-floor">
 <div class="v6-floor-label">@include('explorer.icon',['icon'=>'layers'])<div><strong>{{ $floor==='غير محدد'?'الطابق غير محدد':\App\Models\Unit::floorLabel((int)$floor) }}</strong><small>{{ $floorUnits->count() }} في هذه الصفحة</small></div></div>
 <div class="v6-unit-grid">
 @foreach($floorUnits as $unit)
 @php($state=$unit->occupancy_status)
 @php($detailUrl=route('modules.preview',['units',$unit->id]))
 @php($peek=['code'=>$unit->code,'floor'=>$unit->floor_label,'type'=>$types[$unit->type]??$unit->type,'status'=>$labels[$state],'state'=>$state,'detailUrl'=>$detailUrl,'detailTitle'=>'الوحدة '.$unit->code])
 <div class="v6-unit v6-state-{{ $state }}">
  <button type="button" class="v6-peek" aria-haspopup="dialog" aria-expanded="false" aria-label="معاينة سريعة للوحدة {{ $unit->code }}" data-peek='@json($peek)'>@include('explorer.icon',['icon'=>'eye'])</button>
  <a class="v6-unit-main view-detail-btn" href="{{ $detailUrl }}" data-modal-url="{{ $detailUrl }}" data-modal-title="الوحدة {{ $unit->code }}">
   @include('explorer.icon',['icon'=>$icons[$state]])
   <strong>{{ $unit->code }}</strong><span>{{ $types[$unit->type]??$unit->type }}</span>
   <small class="v6-unit-badge">@include('explorer.icon',['icon'=>$icons[$state]]) {{ $labels[$state] }}</small>
  </a>
 </div>
 @endforeach
 </div>
</section>
@empty
<div class="panel empty-state"><strong>لا توجد وحدات مطابقة لهذه الفلاتر.</strong><p>جرّب تضييق أو تغيير معايير البحث.</p><a class="btn btn-outline" href="{{ route('explorer.show',$item) }}">إعادة الضبط</a></div>
@endforelse
</div>
{{ $units->links() }}
@include('modules.detail-dialog')

<div id="v6QuickPreview" class="v6-quickpreview" hidden role="dialog" aria-label="معاينة سريعة للوحدة">
 <div class="v6-quickpreview-head"><strong data-peek-code></strong><span class="v6-quickpreview-status" data-peek-status></span></div>
 <dl class="v6-quickpreview-meta">
  <div><dt>الطابق</dt><dd data-peek-floor></dd></div>
  <div><dt>النوع</dt><dd data-peek-type></dd></div>
 </dl>
 <a class="btn btn-outline view-detail-btn" data-peek-link href="#">عرض التفاصيل الكاملة ←</a>
</div>
@push('scripts')<script src="{{ asset('assets/js/explorer.js') }}?v=1" defer></script>@endpush
@endsection
