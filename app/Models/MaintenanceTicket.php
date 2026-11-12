<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class MaintenanceTicket extends Model {
 protected $table='maintenance_tickets';
 protected $guarded=['id'];
 public function property() {return $this->belongsTo(Property::class);}
 public function unit() {return $this->belongsTo(Unit::class);}
 public function assignee() {return $this->belongsTo(User::class,'assigned_to');}
}
