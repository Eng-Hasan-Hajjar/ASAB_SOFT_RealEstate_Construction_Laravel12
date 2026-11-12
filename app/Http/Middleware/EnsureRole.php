<?php
namespace App\Http\Middleware;
use App\Support\Workspace;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
class EnsureRole {
 public function handle(Request $request,Closure $next,string ...$roles):Response {
  abort_unless($request->user(),403);
  // The platform owner always retains access to /platform to exit an assumed workspace.
  $allowed=in_array(Workspace::role(),$roles,true)||($request->user()->isPlatformOwner()&&in_array('super_admin',$roles,true));
  abort_unless($allowed,403,'لا تملك صلاحية الوصول.');
  return $next($request);
 }
}
