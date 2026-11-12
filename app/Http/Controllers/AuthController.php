<?php
namespace App\Http\Controllers;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
class AuthController extends Controller {
 public function create(){return view('auth.login');}
 public function store(Request $request){
  $credentials=$request->validate(['email'=>['required','email'],'password'=>['required','string']]);
  if (!Auth::attempt($credentials,$request->boolean('remember'))) throw ValidationException::withMessages(['email'=>'بيانات الدخول غير صحيحة.']);
  $request->session()->regenerate();
  return redirect()->intended(Auth::user()->isPlatformOwner()?route('platform.index'):route('dashboard'));
 }
 public function destroy(Request $request){Auth::logout();$request->session()->invalidate();$request->session()->regenerateToken();return redirect()->route('login');}
}
