<?php
namespace App\Http\Controllers;
use App\Models\{Property,PropertyAccessGrant,User};
use App\Services\Audit;
use App\Support\PropertyAccess;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
class StaffController extends CompanyBaseController {
 private const ROLES=['company_admin','manager','agent','accountant','viewer'];
 public function index(){ $users=User::where('company_id',$this->companyId())->orderBy('name')->paginate(20);return view('settings.staff',compact('users')); }
 public function store(Request $request){
  $data=$request->validate(['name'=>['required','string','max:255'],'email'=>['required','email','max:255','unique:users,email'],
   'password'=>['required','string','min:12','confirmed'],'role'=>['required',Rule::in(self::ROLES)]]);
  $user=User::create([...$data,'company_id'=>$this->companyId(),'is_active'=>true]);
  Audit::record('created','users',$user->id,'إضافة موظف');return back()->with('success','تم إضافة الموظف.');
 }
 public function toggle(User $user){
  abort_unless((int)$user->company_id===$this->companyId(),404);
  abort_if($user->id===auth()->id(),422,'لا يمكنك تعطيل حسابك.');
  $user->update(['is_active'=>!$user->is_active]);
  Audit::record('updated','users',$user->id,'تغيير حالة موظف');return back()->with('success','تم تحديث الموظف.');
 }
 // Per-employee, per-property permissions: which properties this employee can see at all, and
 // independently, whether they may see the sale price, see the rent price, and/or edit either
 // price — all enforced server-side via App\Support\PropertyAccess, not merely hidden in the UI.
 public function permissions(User $user){
  abort_unless((int)$user->company_id===$this->companyId(),404);
  $properties=Property::where('company_id',$this->companyId())->orderBy('name')->get(['id','name','city']);
  $grants=$user->propertyGrants()->get()->keyBy('property_id');
  return view('settings.staff-permissions',compact('user','properties','grants'));
 }
 public function updatePermissions(Request $request,User $user){
  abort_unless((int)$user->company_id===$this->companyId(),404);
  $companyId=$this->companyId();
  $data=$request->validate([
   'role'=>['required',Rule::in(self::ROLES)],
   'scope_properties'=>['sometimes','boolean'],
   'grants'=>['array'],
   'grants.*.property_id'=>['required','integer',Rule::exists('properties','id')->where('company_id',$companyId)],
   'grants.*.can_view_property'=>['sometimes','boolean'],
   'grants.*.can_view_sale_price'=>['sometimes','boolean'],
   'grants.*.can_view_rent_price'=>['sometimes','boolean'],
   'grants.*.can_edit_prices'=>['sometimes','boolean'],
  ]);
  if($user->id===auth()->id() && $data['role']!==$user->role)throw ValidationException::withMessages(['role'=>'لا يمكنك تغيير دورك الخاص.']);
  DB::transaction(function() use ($user,$data,$companyId){
   $user->update(['role'=>$data['role'],'scope_properties'=>(bool)($data['scope_properties']??false)]);
   $submitted=collect($data['grants']??[])->keyBy('property_id');
   foreach($submitted as $propertyId=>$row){
    $flags=['can_view_property'=>(bool)($row['can_view_property']??false),'can_view_sale_price'=>(bool)($row['can_view_sale_price']??false),
     'can_view_rent_price'=>(bool)($row['can_view_rent_price']??false),'can_edit_prices'=>(bool)($row['can_edit_prices']??false)];
    if(array_filter($flags)){
     PropertyAccessGrant::updateOrCreate(['user_id'=>$user->id,'property_id'=>$propertyId],['company_id'=>$companyId]+$flags);
    } else {
     PropertyAccessGrant::where('user_id',$user->id)->where('property_id',$propertyId)->delete();
    }
   }
   PropertyAccessGrant::where('user_id',$user->id)->whereNotIn('property_id',$submitted->keys())->delete();
  });
  PropertyAccess::forgetCache($user->id);
  Audit::record('updated','users',$user->id,'تحديث صلاحيات ودور الموظف');
  return back()->with('success','تم حفظ الصلاحيات.');
 }
}
