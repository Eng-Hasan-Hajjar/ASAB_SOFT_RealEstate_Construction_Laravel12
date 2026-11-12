<?php
namespace App\Http\Controllers;
use App\Models\{Property,PropertyInquiry,PropertyMedia,Unit};
use App\Services\Audit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
class MarketplaceController extends CompanyBaseController {
 private function property(int $id):Property{return Property::where('company_id',$this->companyId())->findOrFail($id);}
 private function writable():void {abort_unless(in_array($this->role(),['company_admin','manager','agent'],true),403);}
 public function index(){
  $c=$this->companyId();$properties=Property::where('company_id',$c)->with(['cover'])
   ->withCount(['units','units as live_units_count'=>fn($q)=>$q->where('status','available')->where('is_listed',true)])
   ->latest('id')->paginate(12);
  $stats=['properties'=>Property::where('company_id',$c)->count(),
   'published'=>Property::where('company_id',$c)->where('is_published',true)->count(),
   'listed_units'=>Unit::where('company_id',$c)->where('is_listed',true)->where('status','available')->count(),
   'new_inquiries'=>PropertyInquiry::where('company_id',$c)->where('status','new')->count()];
  return view('marketplace.index',compact('properties','stats'));
 }
 public function edit(int $property){
  $record=$this->property($property)->load(['media','units']);
  return view('marketplace.edit',compact('record'));
 }
 public function save(Request $request,int $property){
  $this->writable();$record=$this->property($property);
  $data=$request->validate(['public_title'=>['nullable','string','max:180'],
   'public_summary'=>['nullable','string','max:2200'],'latitude'=>['nullable','numeric','between:-90,90'],
   'longitude'=>['nullable','numeric','between:-180,180']]);
  $record->update($data);Audit::record('updated','property_listing',$record->id,'تحديث بيانات عرض العقار');
  return back()->with('success','تم حفظ بيانات الإعلان.');
 }
 public function toggle(Request $request,int $property){
  $this->writable();$record=$this->property($property);
  if(!$record->is_published && $record->status!=='active')throw ValidationException::withMessages(['publish'=>'لا يمكن نشر عقار غير نشط. حدّث حالة العقار أولاً.']);
  if(!$record->is_published && !$record->units()->where('status','available')->where('is_listed',true)->exists())
    throw ValidationException::withMessages(['publish'=>'قبل النشر، أضف وحدة متاحة وحدّد إظهارها في السوق.']);
  $record->update(['is_published'=>!$record->is_published,'published_at'=>!$record->is_published?now():null]);
  Audit::record('updated','property_listing',$record->id,$record->is_published?'نشر إعلان عقار':'إخفاء إعلان عقار');
  return back()->with('success',$record->is_published?'تم النشر في الواجهة العامة.':'تم إلغاء النشر.');
 }
 public function feature(int $property){
  $this->writable();$record=$this->property($property);
  $record->update(['is_featured'=>!$record->is_featured]);
  Audit::record('updated','property_listing',$record->id,'تغيير تمييز العقار');return back()->with('success','تم تحديث العرض المميز.');
 }
 public function unit(Request $request,int $property,int $unit){
  $this->writable();$record=$this->property($property);
  $item=$record->units()->where('company_id',$this->companyId())->findOrFail($unit);
  $data=$request->validate(['listing_type'=>['required',Rule::in(['rent','sale','both'])],
    'sale_price'=>['nullable','numeric','min:0','max:1000000000','decimal:0,3'],
    'furnished'=>['sometimes','boolean'],'is_listed'=>['sometimes','boolean']]);
  if(in_array($data['listing_type'],['sale','both'],true)&&empty($data['sale_price']))throw ValidationException::withMessages(['sale_price'=>'أدخل سعر البيع عندما يتضمن الإعلان البيع.']);
  if($request->boolean('is_listed')&&$item->status!=='available')throw ValidationException::withMessages(['is_listed'=>'الوحدات المؤجرة أو غير المتاحة لا يمكن عرضها في السوق.']);
  $item->update(['listing_type'=>$data['listing_type'],'sale_price'=>$data['sale_price']??null,
   'furnished'=>$request->boolean('furnished'),'is_listed'=>$request->boolean('is_listed')]);
  if($record->is_published && !$record->units()->where('status','available')->where('is_listed',true)->exists()){
   $record->update(['is_published'=>false,'published_at'=>null]);
  }
  Audit::record('updated','unit_listing',$item->id,'تعديل بيانات عرض الوحدة');return back()->with('success','تم تحديث عرض الوحدة.');
 }
 public function upload(Request $request,int $property){
  $this->writable();$record=$this->property($property);
  $data=$request->validate(['photo'=>['required','image','mimes:jpg,jpeg,png,webp','max:3072'],
   'alt'=>['nullable','string','max:180']]);
  if($record->media()->count()>=12)throw ValidationException::withMessages(['photo'=>'الحد الأقصى 12 صورة للعقار.']);
  $path=$request->file('photo')->store('listings/'.$this->companyId().'/'.$record->id,'public');
  try {
   $isCover=!$record->media()->exists();
   $media=$record->media()->create(['company_id'=>$this->companyId(),'path'=>$path,'alt'=>$data['alt']??$record->name,
     'is_cover'=>$isCover,'sort_order'=>$record->media()->count()]);
   Audit::record('created','property_media',$media->id,'إضافة صورة للعقار');
  } catch(\Throwable $e){Storage::disk('public')->delete($path);throw $e;}
  return back()->with('success','تم رفع الصورة بنجاح.');
 }
 public function cover(int $property,int $media){
  $this->writable();$record=$this->property($property);
  $image=$record->media()->where('company_id',$this->companyId())->findOrFail($media);
  DB::transaction(function()use($record,$image){$record->media()->update(['is_cover'=>false]);$image->update(['is_cover'=>true]);});
  Audit::record('updated','property_media',$image->id,'تعيين صورة الغلاف');return back()->with('success','تم تعيين الغلاف.');
 }
 public function deleteImage(int $property,int $media){
  $this->writable();$record=$this->property($property);$image=$record->media()->where('company_id',$this->companyId())->findOrFail($media);
  $path=$image->path;$wasCover=$image->is_cover;
  DB::transaction(function()use($image,$record,$wasCover){$image->delete();if($wasCover){$next=$record->media()->first();if($next)$next->update(['is_cover'=>true]);}});
  Storage::disk('public')->delete($path);Audit::record('deleted','property_media',$media,'حذف صورة العقار');return back()->with('success','تم حذف الصورة.');
 }
 public function inquiries(){
  $inquiries=PropertyInquiry::where('company_id',$this->companyId())->with(['property:id,name','unit:id,code'])
   ->latest()->paginate(20);return view('marketplace.inquiries',compact('inquiries'));
 }
 public function inquiryUpdate(Request $request,int $inquiry){
  $this->writable();$record=PropertyInquiry::where('company_id',$this->companyId())->findOrFail($inquiry);
  $data=$request->validate(['status'=>['required',Rule::in(['new','contacted','qualified','closed'])]]);
  $record->update($data);Audit::record('updated','property_inquiry',$record->id,'تغيير حالة استفسار عقاري');
  return back()->with('success','تم تحديث حالة الاستفسار.');
 }
}
