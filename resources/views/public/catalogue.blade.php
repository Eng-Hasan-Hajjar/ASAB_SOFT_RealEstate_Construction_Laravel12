@extends('public.layout')
@section('title','تصفح العقارات')
@section('content')
<section class="subhero"><div class="pub-container"><span class="eyebrow light">PROPERTY CATALOGUE</span><h1>استكشف العقارات المتاحة</h1><p>تصفح الإعلانات المنشورة، وقارن الوحدات حسب الموقع والنوع والميزانية.</p></div></section>
<section class="pub-section catalogue-section"><div class="pub-container"><div class="catalogue-layout">
<aside class="filter-panel"><div class="filter-title"><h2>فلترة النتائج</h2><a href="{{ route('public.catalogue') }}">مسح</a></div><form method="GET" action="{{ route('public.catalogue') }}" class="filter-form">
<label>كلمة البحث<input name="q" value="{{ $filters['q']??'' }}" placeholder="حي، اسم العقار، مدينة"></label>
<label>الدولة<select name="country"><option value="">كل الدول</option><option value="SA" @selected(($filters['country']??'')==='SA')>السعودية</option><option value="BH" @selected(($filters['country']??'')==='BH')>البحرين</option></select></label>
<label>المدينة<input name="city" value="{{ $filters['city']??'' }}" placeholder="مثل الدمام أو المنامة"></label>
<label>نوع الوحدة<select name="type"><option value="">كل الأنواع</option>@foreach(['apartment'=>'شقة','villa'=>'فيلا','office'=>'مكتب','shop'=>'محل','warehouse'=>'مستودع','other'=>'أخرى'] as $k=>$v)<option value="{{ $k }}" @selected(($filters['type']??'')===$k)>{{ $v }}</option>@endforeach</select></label>
<label>نوع العرض<select name="intent"><option value="">الكل</option><option value="rent" @selected(($filters['intent']??'')==='rent')>إيجار</option><option value="sale" @selected(($filters['intent']??'')==='sale')>بيع</option></select></label>
<div class="filter-pair"><label>السعر من<input type="number" min="0" name="min" value="{{ $filters['min']??'' }}" placeholder="0"></label><label>إلى<input type="number" min="0" name="max" value="{{ $filters['max']??'' }}" placeholder="الحد الأعلى"></label></div>
<small class="filter-note">السعر بعملة الدولة: ريال سعودي أو دينار بحريني. اختر الدولة للمقارنة الدقيقة.</small>
<label>غرف على الأقل<input type="number" name="rooms" min="0" max="20" value="{{ $filters['rooms']??'' }}"></label>
<label>الترتيب<select name="sort"><option value="recent">الأحدث</option><option value="rent_asc" @selected(($filters['sort']??'')==='rent_asc')>الإيجار: من الأقل</option><option value="rent_desc" @selected(($filters['sort']??'')==='rent_desc')>الإيجار: من الأعلى</option></select></label>
<button type="submit" class="pub-btn">تطبيق الفلاتر ←</button></form></aside>
<div class="results-area"><div class="results-top"><div><span class="eyebrow">AVAILABLE PROPERTIES</span><h2>{{ $properties->total() }} عقار مطابق</h2></div><span class="results-note">البيانات المنشورة فقط</span></div>
<div class="listing-grid">@forelse($properties as $property)@include('public.partials.card',['property'=>$property])@empty<div class="empty-results"><span>⌕</span><h3>لم نعثر على عقارات مطابقة</h3><p>جرّب إزالة بعض الفلاتر أو توسيع نطاق البحث.</p><a href="{{ route('public.catalogue') }}" class="pub-btn">عرض الكل</a></div>@endforelse</div>
<div class="pagination-wrap">{{ $properties->links() }}</div></div>
</div></div></section>
@endsection