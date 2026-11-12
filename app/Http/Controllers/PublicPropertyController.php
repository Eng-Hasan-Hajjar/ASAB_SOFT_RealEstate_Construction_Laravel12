<?php
namespace App\Http\Controllers;
use App\Models\{Company,Lead,Property,PropertyInquiry,Unit};
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
class PublicPropertyController extends Controller {
 private function visible(){return Property::query()->where('is_published',true)->where('status','active')
  ->whereHas('company',fn($q)=>$q->where('is_active',true))
  ->whereHas('units',fn($q)=>$q->where('is_listed',true)->where('status','available'));}
 private function cardData($q){return $q->with(['cover','company:id,name,currency,country_code'])
  ->withCount(['units as live_units_count'=>fn($q)=>$q->where('is_listed',true)->where('status','available')])
  ->withMin(['units as lowest_rent'=>fn($q)=>$q->where('is_listed',true)->where('status','available')->whereIn('listing_type',['rent','both'])],'monthly_rent')
  ->withMin(['units as lowest_sale'=>fn($q)=>$q->where('is_listed',true)->where('status','available')->whereIn('listing_type',['sale','both'])->whereNotNull('sale_price')],'sale_price');}
 public function home(){
  $latest=$this->cardData($this->visible()->orderByDesc('is_featured')->latest('published_at'))->limit(6)->get();
  $metrics=['listings'=>$this->visible()->count(),'cities'=>$this->visible()->distinct('city')->count('city')];
  return view('public.home',compact('latest','metrics'));
 }
 public function index(Request $request){
  $filters=$request->validate([
   'q'=>['nullable','string','max:120'],'country'=>['nullable',Rule::in(['SA','BH'])],
   'city'=>['nullable','string','max:100'],'type'=>['nullable',Rule::in(['apartment','villa','office','shop','warehouse','other'])],
   'intent'=>['nullable',Rule::in(['rent','sale'])],'min'=>['nullable','numeric','min:0','max:1000000000'],
   'max'=>['nullable','numeric','min:0','max:1000000000'],'rooms'=>['nullable','integer','between:0,20'],
   'sort'=>['nullable',Rule::in(['recent','rent_asc','rent_desc'])],
  ]);
  $q=$this->visible();
  if(!empty($filters['q'])){$term=$filters['q'];$q->where(fn($x)=>$x->where('name','like',"%$term%")->orWhere('city','like',"%$term%")->orWhere('district','like',"%$term%"));}
  if(!empty($filters['country']))$q->where('country_code',$filters['country']);
  if(!empty($filters['city']))$q->where('city','like','%'.$filters['city'].'%');
  $q->whereHas('units',function($u)use($filters){
    $u->where('is_listed',true)->where('status','available');
    if(!empty($filters['type']))$u->where('type',$filters['type']);
    if(!empty($filters['rooms']))$u->where('rooms','>=',$filters['rooms']);
    if(!empty($filters['intent'])){
     $u->whereIn('listing_type',[$filters['intent'],'both']);
     if($filters['intent']==='sale')$u->whereNotNull('sale_price');
    }
    if(isset($filters['min'])||isset($filters['max'])){
     // All price ranges are in the property's own currency. Restrict currency comparison by country when selecting it.
     $column=($filters['intent']??'rent')==='sale'?'sale_price':'monthly_rent';
     if(isset($filters['min']))$u->where($column,'>=',$filters['min']);
     if(isset($filters['max']))$u->where($column,'<=',$filters['max']);
    }
  });
  $q=$this->cardData($q);
  $sort=$filters['sort']??'recent';
  if($sort==='rent_asc')$q->orderBy('lowest_rent'); elseif($sort==='rent_desc')$q->orderByDesc('lowest_rent');
  else $q->orderByDesc('is_featured')->latest('published_at');
  $properties=$q->paginate(9)->withQueryString();
  return view('public.catalogue',compact('properties','filters'));
 }
 public function show(Property $property){
  abort_unless($property->is_published&&$property->status==='active'&&$property->company?->is_active,404);
  $units=$property->units()->where('is_listed',true)->where('status','available')->orderBy('monthly_rent')->get();
  abort_if($units->isEmpty(),404);
  $property->load(['media','company']);
  return view('public.property',compact('property','units'));
 }
 public function inquire(Request $request,Property $property){
  abort_unless($property->is_published&&$property->status==='active'&&$property->company?->is_active,404);
  $data=$request->validate([
   'name'=>['required','string','min:2','max:160'],'phone'=>['required','string','min:8','max:40'],
   'email'=>['nullable','email','max:255'],'message'=>['nullable','string','max:2000'],
   'unit_id'=>['nullable','integer',Rule::exists('units','id')->where(fn($q)=>$q->where('property_id',$property->id)->where('company_id',$property->company_id)->where('is_listed',true)->where('status','available'))],
   'privacy_accepted'=>['accepted'],'website'=>['nullable','max:0'],
  ]);
  DB::transaction(function()use($property,$data){
   PropertyInquiry::create([
    'company_id'=>$property->company_id,'property_id'=>$property->id,'unit_id'=>$data['unit_id']??null,
    'name'=>$data['name'],'phone'=>$data['phone'],'email'=>$data['email']??null,'message'=>$data['message']??null,'privacy_accepted'=>true,'status'=>'new']);
   Lead::create(['company_id'=>$property->company_id,'name'=>$data['name'],'phone'=>$data['phone'],
     'email'=>$data['email']??null,'interest'=>'rent','stage'=>'new',
     'notes'=>'استفسار عام من موقع العقارات: '.$property->name.(!empty($data['unit_id'])?'، الوحدة #'.$data['unit_id']:'').'. '.($data['message']??'')]);
  });
  return redirect()->route('public.property',$property)->with('success','تم تسجيل طلبك بنجاح وسيتواصل معك فريق الشركة عند المتابعة.');
 }
}
