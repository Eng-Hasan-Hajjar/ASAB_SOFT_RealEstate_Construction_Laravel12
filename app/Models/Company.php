<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
class Company extends Model {
 protected $guarded=['id']; protected function casts():array {return ['is_active'=>'boolean'];}
 public function users():HasMany {return $this->hasMany(User::class);}
 public function getCurrencySymbolAttribute():string {return $this->currency==='BHD'?'د.ب':'ر.س';}
 public function money($amount):string {return number_format((float)$amount,$this->currency==='BHD'?3:2).' '.$this->currency_symbol;}
}
