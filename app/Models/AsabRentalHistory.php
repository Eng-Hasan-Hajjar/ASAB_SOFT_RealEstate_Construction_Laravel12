<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

final class AsabRentalHistory extends Model {
    protected $table = 'asab_rental_history';
    protected $guarded = ['id'];
    protected function casts(): array { return ['starts_on'=>'date', 'ends_on'=>'date','source_amount'=>'decimal:3', 'source_deposit'=>'decimal:3']; }
    public function property() { return $this->belongsTo(Property::class); }
    public function unit() { return $this->belongsTo(Unit::class); }
    public function contact() { return $this->belongsTo(Contact::class); }
}
