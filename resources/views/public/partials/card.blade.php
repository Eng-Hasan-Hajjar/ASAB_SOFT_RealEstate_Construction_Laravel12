<article class="listing-card">
<a class="listing-image" href="{{ route('public.property',$property) }}" aria-label="تفاصيل {{ $property->name }}">
@if($property->cover)<img src="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($property->cover->path) }}" loading="lazy" alt="{{ $property->cover->alt?:$property->name }}">@else<img src="{{ asset('assets/images/property-placeholder.svg') }}" loading="lazy" alt="صورة توضيحية للعقار">@endif
@if($property->is_featured)<span class="feature-chip">✦ إعلان مميز</span>@endif
<span class="country-chip">{{ $property->country_code==='BH'?'🇧🇭 البحرين':'🇸🇦 السعودية' }}</span>
</a>
<div class="listing-body"><div class="listing-sub">{{ $property->city }} @if($property->district) · {{ $property->district }} @endif <span>· {{ $property->live_units_count }} وحدة متاحة</span></div>
<h3><a href="{{ route('public.property',$property) }}">{{ $property->public_title?:$property->name }}</a></h3>
<p>{{ \Illuminate\Support\Str::limit($property->public_summary?:$property->description?:'استكشف الوحدات المتاحة ومواصفاتها وتواصل مع الشركة العارضة.',95) }}</p>
<div class="listing-foot"><div><small>{{ $property->lowest_rent!==null?'الإيجار الشهري يبدأ من':'البيع يبدأ من' }}</small><strong dir="ltr">{{ $property->lowest_rent!==null?$property->company->money($property->lowest_rent):($property->lowest_sale!==null?$property->company->money($property->lowest_sale):'السعر عند الاستفسار') }}</strong></div><a class="round-go" aria-label="عرض التفاصيل" href="{{ route('public.property',$property) }}">↗</a></div></div>
</article>