<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class Contact extends Model {
 protected function casts():array {return ['identity_reference'=>'encrypted'];}
 protected $table='contacts';
 protected $guarded=['id'];
 public function leases(){return $this->hasMany(Lease::class);}
 public function rentalHistory(){return $this->hasMany(AsabRentalHistory::class);}
 public function activeLeases(){return $this->hasMany(Lease::class)->current();}
}
