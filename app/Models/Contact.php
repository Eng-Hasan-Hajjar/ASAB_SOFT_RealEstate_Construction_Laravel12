<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class Contact extends Model {
 protected function casts():array {return ['identity_reference'=>'encrypted'];}
 protected $table='contacts';
 protected $guarded=['id'];
}
