<?php
namespace App\Support;
use App\Models\Company;
final class Workspace {
 public static function acting():bool { return auth()->check() && auth()->user()->isPlatformOwner() && session()->has('platform_company_id'); }
 public static function companyId():?int { return self::acting() ? (int)session('platform_company_id') : auth()->user()?->company_id; }
 public static function company():?Company {return self::companyId()?Company::find(self::companyId()):null;}
 public static function role():string {return self::acting()?'company_admin':(auth()->user()?->role??'guest');}
 public static function ownerHome():bool {return auth()->check()&&auth()->user()->isPlatformOwner()&&!self::acting();}
}
