<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

final class AsabFinancialHistory extends Model {
    protected $table = 'asab_financial_history';
    protected $guarded = ['id'];
    protected function casts(): array {return ['event_on'=>'date', 'source_amount'=>'decimal:3'];}
    public function property() {return $this->belongsTo(Property::class);}
    public function unit() {return $this->belongsTo(Unit::class);}
}
