<?php
namespace App\Http\Controllers;
use App\Models\{Company,User};
use App\Services\Audit;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
class PlatformController extends Controller {
 public function index(){
  $companies=Company::query()->withCount('users')->orderByDesc('id')->paginate(15);
  $totals=['companies'=>Company::count(),'active'=>Company::where('is_active',true)->count(),'users'=>User::count()];
  return view('platform.index',compact('companies','totals'));
 }
 public function store(Request $request){
  $data=$request->validate([
   'name'=>['required','string','max:160'],'slug'=>['required','alpha_dash','max:100','unique:companies,slug'],
   'country_code'=>['required',Rule::in(['SA','BH'])],'city'=>['nullable','string','max:100'],
   'owner_name'=>['required','string','max:255'],'owner_email'=>['required','email','max:255','unique:users,email'],
   'owner_password'=>['required','string','min:12','confirmed'],
  ]);
  $company=DB::transaction(function()use($data){
   $company=Company::create(['name'=>$data['name'],'slug'=>$data['slug'],'country_code'=>$data['country_code'],'currency'=>$data['country_code']==='BH'?'BHD':'SAR','city'=>$data['city']??null,'is_active'=>true]);
   User::create(['company_id'=>$company->id,'name'=>$data['owner_name'],'email'=>$data['owner_email'],'password'=>$data['owner_password'],'role'=>'company_admin','is_active'=>true]);
   return $company;
  });
  Audit::record('created','companies',$company->id,'إضافة شركة جديدة',null);
  return back()->with('success','تم إنشاء الشركة وحساب مديرها بنجاح.');
 }
 public function toggle(Company $company){ $company->update(['is_active'=>!$company->is_active]);Audit::record('updated','companies',$company->id,'تغيير حالة الشركة',null);return back()->with('success','تم تحديث حالة الشركة.'); }
 public function enter(Request $request,Company $company){
  abort_unless($company->is_active,422,'الشركة متوقفة.');
  $request->session()->put('platform_company_id',$company->id);
  Audit::record('access','company_workspace',$company->id,'دخول مالك المنصة إلى مساحة الشركة',$company->id);
  return redirect()->route('dashboard')->with('success','أنت تعمل الآن داخل شركة '.$company->name.'.');
 }
 public function leave(Request $request){$request->session()->forget('platform_company_id');return redirect()->route('platform.index')->with('success','عدت إلى لوحة مالك المنصة.');}
}
