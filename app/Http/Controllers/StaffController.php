<?php
namespace App\Http\Controllers;
use App\Models\User;
use App\Services\Audit;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
class StaffController extends CompanyBaseController {
 public function index(){ $users=User::where('company_id',$this->companyId())->orderBy('name')->paginate(20);return view('settings.staff',compact('users')); }
 public function store(Request $request){
  $data=$request->validate(['name'=>['required','string','max:255'],'email'=>['required','email','max:255','unique:users,email'],
   'password'=>['required','string','min:12','confirmed'],'role'=>['required',Rule::in(['company_admin','manager','agent','accountant','viewer'])]]);
  $user=User::create([...$data,'company_id'=>$this->companyId(),'is_active'=>true]);
  Audit::record('created','users',$user->id,'إضافة موظف');return back()->with('success','تم إضافة الموظف.');
 }
 public function toggle(User $user){
  abort_unless((int)$user->company_id===$this->companyId(),404);
  abort_if($user->id===auth()->id(),422,'لا يمكنك تعطيل حسابك.');
  $user->update(['is_active'=>!$user->is_active]);
  Audit::record('updated','users',$user->id,'تغيير حالة موظف');return back()->with('success','تم تحديث الموظف.');
 }
}
