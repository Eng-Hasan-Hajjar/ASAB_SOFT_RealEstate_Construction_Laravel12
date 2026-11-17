<?php
namespace App\Models;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
class User extends Authenticatable {
 use Notifiable;
 protected $fillable=['company_id','name','email','password','role','is_active','scope_properties'];
 protected $hidden=['password','remember_token'];
 protected function casts():array {return ['email_verified_at'=>'datetime','password'=>'hashed','is_active'=>'boolean','scope_properties'=>'boolean'];}
 public function company():BelongsTo {return $this->belongsTo(Company::class);}
 public function propertyGrants():HasMany {return $this->hasMany(PropertyAccessGrant::class);}
 public function isPlatformOwner():bool {return $this->role==='super_admin';}
 public function canManage():bool {return in_array($this->role,['company_admin','manager'],true);}
 public function canEdit():bool {return in_array($this->role,['company_admin','manager','agent','accountant'],true);}
 // company_admin/manager and the platform owner always keep full access to every property in
 // their company; the scope_properties flag only ever restricts other roles (agent/accountant/
 // viewer). See App\Support\PropertyAccess for how this is enforced across controllers.
 public function isPropertyScoped():bool {return (bool)$this->scope_properties && !$this->canManage() && !$this->isPlatformOwner();}
}
