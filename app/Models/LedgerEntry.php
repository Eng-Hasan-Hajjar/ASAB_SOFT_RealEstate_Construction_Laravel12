<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class LedgerEntry extends Model {
 protected $guarded=['id'];
 protected $casts=['posted_on'=>'date:Y-m-d','amount'=>'decimal:3'];
 public function account(){return $this->belongsTo(FinancialAccount::class,'financial_account_id');}
 public function property(){return $this->belongsTo(Property::class);}
 public function unit(){return $this->belongsTo(Unit::class);}
 public function contact(){return $this->belongsTo(Contact::class);}
}
