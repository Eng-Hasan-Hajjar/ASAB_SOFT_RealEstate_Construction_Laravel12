<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class ConstructionProject extends Model {
 protected $table='construction_projects';
 protected $guarded=['id'];
 public function property() {return $this->belongsTo(Property::class);}
 public function tasks() {return $this->hasMany(ProjectTask::class,'construction_project_id');}
 public function expenses() {return $this->hasMany(ProjectExpense::class,'construction_project_id');}
}
