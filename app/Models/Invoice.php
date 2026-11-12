<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class Invoice extends Model {
 protected $table='invoices';
 protected $guarded=['id'];
 public function lease() {return $this->belongsTo(Lease::class);}
 public function contact() {return $this->belongsTo(Contact::class);}
 public function payments() {return $this->hasMany(Payment::class);}
 public function getPaidAttribute():float {return (float)$this->payments()->sum('amount');}
 public function getBalanceAttribute():float {return max(0,round((float)$this->amount-$this->paid,3));}
}
