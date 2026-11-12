<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class PropertyInquiry extends Model {
 protected $table='property_inquiries';protected $guarded=['id'];
 protected function casts():array {return ['privacy_accepted'=>'boolean'];}
 public function property(){return $this->belongsTo(Property::class);}
 public function unit(){return $this->belongsTo(Unit::class);}
}
