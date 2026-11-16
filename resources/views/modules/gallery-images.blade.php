@if(count($galleryImages))
<div class="v6-gallery">
@foreach($galleryImages as $image)
 <figure>
 <a href="{{ route('explorer.image',$image) }}" target="_blank" rel="noopener"><img src="{{ route('explorer.image',$image) }}" alt="{{ $galleryLabel }}" loading="lazy"></a>
 @if(!empty($editable))<label class="v6-delete-image"><input type="checkbox" name="delete_images[]" value="{{ $image->id }}" @checked(in_array((string)$image->id,array_map('strval',(array)old('delete_images',[])),true))> حذف الصورة عند الحفظ</label>@endif
 </figure>
@endforeach
</div>
@endif
