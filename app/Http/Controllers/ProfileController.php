<?php
namespace App\Http\Controllers;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
class ProfileController extends Controller {
 public function edit(){return view('settings.profile');}
 public function update(Request $request){
  $data=$request->validate(['name'=>['required','string','max:255'],'current_password'=>['required','current_password'],
  'new_password'=>['required','confirmed',Password::min(12)->letters()->numbers()]]);
  $request->user()->update(['name'=>$data['name'],'password'=>$data['new_password']]);
  return back()->with('success','تم تحديث كلمة المرور.');
 }
}
