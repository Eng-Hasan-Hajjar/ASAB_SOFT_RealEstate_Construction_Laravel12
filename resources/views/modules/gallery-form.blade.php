@php
 $existingSpaces=($spaces??collect())->keyBy('id');
 $rows=old('spaces_present') ? (array)old('spaces',[]) : $existingSpaces->map(fn($s)=>$s->only(['id','kind','name','description']))->values()->all();
@endphp
<section class="v6-asset-editor" data-gallery-editor>
 <div class="v6-section-head"><div><span class="eyebrow">معرض داخلي</span><h2>الصور والمساحات</h2><p>أضف صورًا عامة، أو اربط صورًا بمساحة محددة. هذه الصور داخلية ولا تُنشر مع الإعلان العام.</p></div></div>
 <label class="filter-control"><span>صور {{ $def['singular'] }} العامة داخل النظام</span><input type="file" name="photos[]" data-photo-input accept="image/jpeg,image/png,image/webp" multiple><span class="v6-photo-previews" data-photo-previews></span></label>
 @include('modules.gallery-images',['galleryImages'=>$images??collect(),'galleryLabel'=>'صورة عامة','editable'=>true])
 <input type="hidden" name="spaces_present" value="1">
 <div class="v6-section-head"><div><h3>المساحات المخصصة</h3><p>الشقة قد تضم غرفًا وحمّامات، والمتجر قد يضم صالة عرض أو مستودعًا فقط. لا توجد مساحات إلزامية.</p>
 @if($module==='units')<p>عند إضافة مساحات مسمّاة، يحسب النظام غرف النوم والحمّامات منها.</p>@endif</div><button type="button" class="btn btn-outline" data-add-space>+ إضافة مساحة</button></div>
 <p class="v6-helper">JPG أو PNG أو WebP، حتى 4 ميغابايت للصورة و12 صورة في عملية الحفظ. أول صورة عامة تظهر على بطاقة العقار.</p>
 <div data-spaces-list>
 @foreach($rows as $i=>$row)
 @if(is_array($row))@include('modules.space-fields',['i'=>$i,'row'=>$row,'spaceImages'=>$existingSpaces->get((int)($row['id']??0))?->images??collect()])@endif
 @endforeach
 </div>
 <template data-space-template>@include('modules.space-fields',['i'=>'__IDX__','row'=>[],'spaceImages'=>collect()])</template>
</section>
