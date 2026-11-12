<?php
namespace App\Http\Middleware;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
class EnsureAccountActive {
 public function handle(Request $request,Closure $next):Response {
  $user=$request->user();
  if (!$user || !$user->is_active || ($user->role !== 'super_admin' && (!$user->company || !$user->company->is_active))) {
   auth()->logout(); $request->session()->invalidate(); $request->session()->regenerateToken();
   return redirect()->route('login')->withErrors(['email'=>'الحساب غير مفعّل. تواصل مع إدارة المنصة.']);
  }
  if ($user->isPlatformOwner() && $request->session()->has('platform_company_id')) {
   $company=\App\Models\Company::find($request->session()->get('platform_company_id'));
   if (!$company || !$company->is_active) $request->session()->forget('platform_company_id');
  }
  return $next($request);
 }
}
