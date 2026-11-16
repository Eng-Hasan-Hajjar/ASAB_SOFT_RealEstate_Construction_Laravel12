@extends('layouts.app')
@section('title','الواجهة التفاعلية')
@section('content')
@php($types=config('modules.units.fields.type.options'))
<section class="v6-hero">
 <div><a href="{{ route('explorer.index') }}">الواجهة التفاعلية ← جميع العقارات</a><h1>{{ $item->name }}</h1><p>{{ $item->city }} {{ $item->district ? '· '.$item->district : '' }} · {{ $units->total() }} وحدة مطابقة</p>
 <div class="v6-tags"><a class="btn btn-outline view-detail-btn" href="{{ route('modules.preview',['properties',$item->id]) }}" data-modal-url="{{ route('modules.preview',['properties',$item->id]) }}" data-modal-title="{{ $item->name }}">تفاصيل العقار</a>
 @if(\App\Support\ModuleRegistry::canWrite(\App\Support\Workspace::role(),'units'))<a class="btn btn-outline" href="{{ route('modules.create',['module'=>'units','property_id'=>$item->id]) }}">+ إضافة وحدة</a>@endif</div>
 </div><div class="v6-hero-icon">@if($cover)<img src="{{ route('explorer.image',$cover) }}" alt="">@else @include('explorer.icon',['icon'=>'building']) @endif</div>
</section>
<form class="panel v6-searchbar" method="get" action="{{ route('explorer.show',$item) }}">
 <label class="filter-control"><span>رمز الوحدة</span><input name="q" type="search" value="{{ request('q') }}" maxlength="120"></label>
 <label class="filter-control"><span>النوع</span><select name="type"><option value="">الكل</option>@foreach($types as $key=>$label)<option value="{{ $key }}" @selected(request('type')===$key)>{{ $label }}</option>@endforeach</select></label>
 <label class="filter-control"><span>الإشغال</span><select name="status"><option value="">الكل</option>@foreach(\App\Models\Unit::statusLabels() as $key=>$label)<option value="{{ $key }}" @selected(request('status')===$key)>{{ $label }}</option>@endforeach</select></label>
 <button class="btn btn-primary">عرض</button><a class="btn btn-outline" href="{{ route('explorer.show',$item) }}">إعادة الضبط</a>
</form>
<div class="v6-building">
@forelse($floors as $floor=>$floorUnits)
<section class="v6-floor">
 <div class="v6-floor-label">@include('explorer.icon',['icon'=>'layers'])<div><strong>{{ $floor==='غير محدد'?'الطابق غير محدد':($floor===0?'الطابق الأرضي':'الطابق '.$floor) }}</strong><small>{{ $floorUnits->count() }} في هذه الصفحة</small></div></div>
 <div class="v6-unit-grid">
 @foreach($floorUnits as $unit)
 @php($state=$unit->occupancy_status)
 <a class="v6-unit v6-state-{{ $state }} view-detail-btn" href="{{ route('modules.preview',['units',$unit->id]) }}" data-modal-url="{{ route('modules.preview',['units',$unit->id]) }}" data-modal-title="الوحدة {{ $unit->code }}">
 @include('explorer.icon',['icon'=>$unit->type])<strong>{{ $unit->code }}</strong><span>{{ $types[$unit->type]??$unit->type }}</span><small>{{ \App\Models\Unit::statusLabels()[$state] }}</small>
 </a>
 @endforeach
 </div>
</section>
@empty
<div class="panel empty-state">لا توجد وحدات مطابقة للعرض.</div>
@endforelse
</div>
{{ $units->links() }}
@include('modules.detail-dialog')
@endsection
