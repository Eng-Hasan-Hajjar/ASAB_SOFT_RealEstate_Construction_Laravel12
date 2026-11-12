<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class KnowledgeEntry extends Model {
 protected $table='knowledge_entries';
 protected $guarded=['id'];
 protected function casts():array {return ['is_published'=>'boolean'];}
}
