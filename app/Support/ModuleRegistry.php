<?php
namespace App\Support;
use Illuminate\Support\Arr;
final class ModuleRegistry {
 public static function all():array {return config('modules',[]);}
 public static function get(string $key):array { $definition=static::all()[$key] ?? null; abort_unless($definition,404); return $definition; }
 public static function modulesForRole(string $role):array {
  if ($role==='viewer') return static::all();
  if ($role==='accountant') return array_intersect_key(static::all(),array_flip(['properties','units','contacts','expenses','projects','knowledge']));
  return static::all();
 }
 public static function canWrite(string $role,string $module):bool {
  if (in_array($role,['company_admin','manager'],true)) return true;
  if ($role==='accountant') return $module==='expenses';
  if ($role==='agent') return in_array($module,['properties','units','contacts','tickets','leads','tasks'],true);
  return false;
 }
}
