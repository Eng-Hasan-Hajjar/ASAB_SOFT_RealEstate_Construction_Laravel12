<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class FinancialAccount extends Model {
 protected $guarded=['id'];
 protected $casts=['is_active'=>'boolean','opening_balance'=>'decimal:3'];
 public function entries(){return $this->hasMany(LedgerEntry::class);}
}
