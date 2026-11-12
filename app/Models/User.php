<?php
namespace App\Models;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
class User extends Authenticatable {
 use Notifiable;
 protected $fillable=['company_id','name','email','password','role','is_active'];
 protected $hidden=['password','remember_token'];
 protected function casts():array {return ['email_verified_at'=>'datetime','password'=>'hashed','is_active'=>'boolean'];}
 public function company():BelongsTo {return $this->belongsTo(Company::class);}
 public function isPlatformOwner():bool {return $this->role==='super_admin';}
 public function canManage():bool {return in_array($this->role,['company_admin','manager'],true);}
 public function canEdit():bool {return in_array($this->role,['company_admin','manager','agent','accountant'],true);}
}
