<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class Property extends Model {
 protected $table='properties';
 protected $guarded=['id'];
 protected function casts():array {return ['is_published'=>'boolean','is_featured'=>'boolean','published_at'=>'datetime'];}
 public function media(){return $this->hasMany(PropertyMedia::class)->orderBy('sort_order')->orderBy('id');}
 public function cover(){return $this->hasOne(PropertyMedia::class)->where('is_cover',true);}
 public function inquiries(){return $this->hasMany(PropertyInquiry::class);}
 public function company(){return $this->belongsTo(Company::class);}
 public function units() {return $this->hasMany(Unit::class);}
}
