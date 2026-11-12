<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class Reservation extends Model {
 protected $guarded=['id'];
 protected $casts=['check_in'=>'date:Y-m-d','check_out'=>'date:Y-m-d','total_price'=>'decimal:3'];
 public function unit(){return $this->belongsTo(Unit::class);}
 public function contact(){return $this->belongsTo(Contact::class);}
}
