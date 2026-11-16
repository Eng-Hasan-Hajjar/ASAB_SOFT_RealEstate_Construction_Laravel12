@extends('layouts.app')
@section('title','الواجهة التفاعلية')
@section('content')
<section class="v6-hero">
 <div><span class="eyebrow">ASAB PROPERTY EXPLORER</span><h1>الواجهة التفاعلية</h1><p>تصفّح محفظة العقارات، واستكشف الشقق والمتاجر طابقًا بطابق.</p></div>
 <div class="v6-hero-icon">@include('explorer.icon',['icon'=>'building'])</div>
</section>
<form class="panel v6-searchbar" method="get" action="{{ route('explorer.index') }}">
 <label class="filter-control"><span>اسم العقار أو المدينة أو الحي</span><input type="search" name="q" maxlength="120" value="{{ request('q') }}" placeholder="ابحث في محفظة العقارات"></label>
 <button class="btn btn-primary">بحث</button><a class="btn btn-outline" href="{{ route('explorer.index') }}">إعادة الضبط</a>
</form>
<div class="v6-property-grid">
@forelse($properties as $property)
<a class="v6-asset-card" href="{{ route('explorer.show',$property) }}">
 <div class="v6-asset-photo">@if($covers->has($property->id))<img loading="lazy" src="{{ route('explorer.image',$covers[$property->id]) }}" alt="">@else @include('explorer.icon',['icon'=>'building']) @endif</div>
 <div class="v6-asset-body"><span class="v6-card-meta">@include('explorer.icon',['icon'=>'pin']) {{ $property->city }} {{ $property->district ? '· '.$property->district : '' }}</span><h2>{{ $property->name }}</h2>
 <div class="v6-tags"><span>{{ $property->units_count }} وحدة</span><span>{{ $property->occupied_count }} مؤجرة</span><span>{{ $property->available_count }} متاحة</span></div><strong>استكشاف العقار ←</strong></div>
</a>
@empty
<div class="panel empty-state"><strong>لا توجد عقارات مطابقة.</strong><p>تظهر هنا العقارات الجديدة تلقائياً.</p></div>
@endforelse
</div>
{{ $properties->links() }}
@endsection
