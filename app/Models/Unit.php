<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class Unit extends Model {
 protected $table='units';
 protected $guarded=['id'];
 protected function casts():array {return ['is_listed'=>'boolean','furnished'=>'boolean'];}
 public function media(){return $this->hasMany(PropertyMedia::class);}
 public function inquiries(){return $this->hasMany(PropertyInquiry::class);}
 public function property() {return $this->belongsTo(Property::class);}
 public function leases() {return $this->hasMany(Lease::class);}
}
