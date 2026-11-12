<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class ImportRow extends Model {
 protected $guarded=['id'];
 protected $casts=['payload'=>'encrypted:array'];
 public function batch(){return $this->belongsTo(ImportBatch::class,'import_batch_id');}
}
