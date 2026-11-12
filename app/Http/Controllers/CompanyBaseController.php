<?php
namespace App\Http\Controllers;
use App\Support\Workspace;
use Illuminate\Routing\Controller;
abstract class CompanyBaseController extends Controller {
 protected function companyId():int {
  $id=Workspace::companyId(); abort_unless($id && Workspace::company()?->is_active,403,'لا توجد مساحة عمل مفعلة.');return (int)$id;
 }
 protected function role():string {return Workspace::role();}
}
