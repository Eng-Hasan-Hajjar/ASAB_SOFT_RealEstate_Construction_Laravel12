<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class Lease extends Model {
 protected $table='leases';
 protected $guarded=['id'];
 public function unit() {return $this->belongsTo(Unit::class);}
 public function contact() {return $this->belongsTo(Contact::class);}
 public function invoices() {return $this->hasMany(Invoice::class);}
}
