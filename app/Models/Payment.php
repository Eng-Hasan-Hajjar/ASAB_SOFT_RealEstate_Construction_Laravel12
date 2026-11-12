<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class Payment extends Model {
 protected $table='payments';
 protected $guarded=['id'];
 public function invoice() {return $this->belongsTo(Invoice::class);}
 public function ledgerEntry() {return $this->hasOne(LedgerEntry::class,'source_payment_id');}
}
