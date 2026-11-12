<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class ImportBatch extends Model {
 protected $guarded=['id'];
 public function rows(){return $this->hasMany(ImportRow::class);}
}
