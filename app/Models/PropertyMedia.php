<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class PropertyMedia extends Model {
 protected $table='property_media';protected $guarded=['id'];
 protected function casts():array{return ['is_cover'=>'boolean'];}
 public function property(){return $this->belongsTo(Property::class);}
 public function unit(){return $this->belongsTo(Unit::class);}
}
