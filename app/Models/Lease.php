<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class Lease extends Model
{
    protected $guarded = ['id'];

    public function unit() { return $this->belongsTo(Unit::class); }
    public function contact() { return $this->belongsTo(Contact::class); }
    public function invoices() { return $this->hasMany(Invoice::class); }

    public function scopeCurrent(Builder $query, ?string $date = null): Builder
    {
        $date ??= today()->toDateString();
        return $query->where('status', 'active')->where('starts_on', '<=', $date)->where('ends_on', '>=', $date);
    }

    public function getEffectiveStatusAttribute(): string
    {
        if ($this->status !== 'active') return $this->status;
        $date = today()->toDateString();
        return $this->ends_on < $date ? 'ended' : ($this->starts_on > $date ? 'upcoming' : 'active');
    }
}
