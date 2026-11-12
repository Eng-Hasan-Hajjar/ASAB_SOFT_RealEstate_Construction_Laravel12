<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class ProjectExpense extends Model {
 protected $table='project_expenses';
 protected $guarded=['id'];
 public function project() {return $this->belongsTo(ConstructionProject::class,'construction_project_id');}
 public function contractor() {return $this->belongsTo(Contractor::class);}
}
