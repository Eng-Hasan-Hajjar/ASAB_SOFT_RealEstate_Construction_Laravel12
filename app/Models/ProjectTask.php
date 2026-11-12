<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class ProjectTask extends Model {
 protected $table='project_tasks';
 protected $guarded=['id'];
 public function project() {return $this->belongsTo(ConstructionProject::class,'construction_project_id');}
}
