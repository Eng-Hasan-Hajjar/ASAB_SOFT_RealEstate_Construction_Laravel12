<article class="v6-space-row" data-space-row>
 @if(!empty($row['id']))<input type="hidden" name="spaces[{{ $i }}][id]" value="{{ $row['id'] }}">@endif
 <div class="v6-space-label"><strong>مساحة مخصصة</strong><button type="button" class="btn btn-outline" data-remove-space>إزالة المساحة</button></div>
 <div class="form-grid">
  <label class="filter-control"><span>نوع المساحة</span><select name="spaces[{{ $i }}][kind]" required>
   @foreach(\App\Models\WorkspaceSpace::kinds() as $key=>$label)<option value="{{ $key }}" @selected(($row['kind']??'bedroom')===$key)>{{ $label }}</option>@endforeach
  </select></label>
  <label class="filter-control"><span>اسمها داخل العقار أو الوحدة</span><input name="spaces[{{ $i }}][name]" value="{{ $row['name']??'' }}" maxlength="120" placeholder="مثل غرفة النوم الأولى أو صالة العرض" required></label>
  <label class="filter-control"><span>وصف المساحة</span><textarea name="spaces[{{ $i }}][description]" maxlength="2000" rows="2">{{ $row['description']??'' }}</textarea></label>
  <label class="filter-control"><span>صور المساحة</span><input type="file" name="spaces[{{ $i }}][photos][]" data-photo-input accept="image/jpeg,image/png,image/webp" multiple><span class="v6-photo-previews" data-photo-previews></span></label>
 </div>
 @include('modules.gallery-images',['galleryImages'=>$spaceImages,'galleryLabel'=>$row['name']??'صورة المساحة','editable'=>true])
</article>
