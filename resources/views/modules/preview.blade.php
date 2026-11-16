<dl class="record-details-grid">
  @foreach($values as $row)
  <div class="record-detail"><dt>{{ $row['label'] }}</dt><dd>{{ $row['value'] }}</dd></div>
  @endforeach
  <div class="record-detail"><dt>معرف السجل</dt><dd>#{{ $item->id }}</dd></div>
  <div class="record-detail"><dt>آخر تحديث</dt><dd>{{ $item->updated_at?->format('Y-m-d H:i') ?? '—' }}</dd></div>
</dl>
@if(in_array($module,['properties','units'],true))
<section class="v6-detail-section">
 <h3>معرض الصور</h3>
 @include('modules.gallery-images',['galleryImages'=>$images,'galleryLabel'=>'صورة عامة','editable'=>false])
 @if($images->isEmpty())<p>لا توجد صور عامة داخلية.</p>@endif
 @if($spaces->isNotEmpty())
 <h3>المساحات المخصصة</h3>
 <div class="v6-space-list">
 @foreach($spaces as $space)
 <article><small>{{ \App\Models\WorkspaceSpace::kinds()[$space->kind]??$space->kind }}</small><h4>{{ $space->name }}</h4>@if($space->description)<p>{{ $space->description }}</p>@endif
 @include('modules.gallery-images',['galleryImages'=>$space->images,'galleryLabel'=>$space->name,'editable'=>false])
 </article>
 @endforeach
 </div>
 @endif
</section>
@endif
@if($module==='properties')
<section class="v6-detail-section"><h3>وحدات العقار</h3>
<div class="v6-detail-list">@forelse($assetUnits as $unit)<a href="{{ route('modules.show',['units',$unit->id]) }}"><strong>{{ $unit->code }}</strong><span>{{ \App\Models\Unit::statusLabels()[$unit->occupancy_status] }}</span></a>@empty<p>لا توجد وحدات مسجلة.</p>@endforelse</div>
<a class="btn btn-outline" href="{{ route('explorer.show',$item) }}">عرض جميع الوحدات في الواجهة التفاعلية</a>
</section>
@endif
@if(in_array($module,['units','contacts'],true))
<section class="v6-detail-section"><h3>الإيجار الحالي والسجل السابق</h3>
 @if(!$leases->contains(fn($l)=>$l->effective_status==='active'))<p>{{ $module==='units'?'لا يوجد مستأجر مرتبط بعقد حالٍ.':'غير مرتبط بعقد إيجار حالي.' }}</p>@endif
 <div class="v6-detail-list">
 @foreach($leases as $lease)
 <a href="{{ route('leases.show',$lease) }}"><strong>{{ $module==='units'?$lease->contact?->name:($lease->unit?->property?->name.' / '.$lease->unit?->code) }} · {{ $lease->contract_number }}</strong><span>{{ ['active'=>'حالي','ended'=>'منتهٍ','upcoming'=>'مجدول','draft'=>'مسودة','cancelled'=>'ملغى'][$lease->effective_status] ?? $lease->effective_status }} · <bdi>{{ $lease->starts_on }} – {{ $lease->ends_on }}</bdi></span></a>
 @endforeach
 </div>
 @if($leases->count()===12)<p>تعرض أحدث 12 علاقة مع تقديم العقود الحالية.</p>@endif
 @if(in_array(\App\Support\Workspace::role(),['company_admin','manager','agent'],true) && ($module==='units'||$item->type==='tenant'))
 <a class="btn btn-outline" href="{{ route('leases.create',[$module==='units'?'unit_id':'contact_id'=>$item->id]) }}">+ تسجيل عقد لهذه العلاقة</a>
 @endif
</section>
@endif

@if($historical->isNotEmpty())
<section class="v6-detail-section"><h3>روابط الإيجار السابقة</h3><p>أحدث 8 روابط تاريخية، لا تدل وحدها على وجود عقد معتمد أو إشغال حالي.</p>
 <div class="v6-detail-list">
 @foreach($historical as $entry)
 <a href="{{ route('asab.history.rentals',['property'=>$entry->property_id,'q'=>$module==='units'?$entry->source_sheet:$entry->tenant_label]) }}">
 <strong>{{ $module==='units'?$entry->contact?->name:($entry->property?->name.' / '.$entry->unit?->code) }}</strong>
 <span><bdi>{{ $entry->date_from_source ?: '—' }} – {{ $entry->date_to_source ?: '—' }}</bdi></span></a>
 @endforeach
 </div>
</section>
@endif
