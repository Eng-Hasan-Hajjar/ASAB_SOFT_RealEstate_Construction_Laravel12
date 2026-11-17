<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class Unit extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array { return ['is_listed'=>'boolean','furnished'=>'boolean']; }
    public function media() { return $this->hasMany(PropertyMedia::class); }
    public function inquiries() { return $this->hasMany(PropertyInquiry::class); }
    public function property() { return $this->belongsTo(Property::class); }
    public function leases() { return $this->hasMany(Lease::class); }
    public function rentalHistory() { return $this->hasMany(AsabRentalHistory::class); }
    public function currentLeases() { return $this->hasMany(Lease::class)->current(); }
    public function approvedLeases() { return $this->hasMany(Lease::class)->where('status','active'); }
    public function reservations() { return $this->hasMany(Reservation::class); }
    public function currentReservations() {
        $date = today()->toDateString();
        return $this->hasMany(Reservation::class)->where('status','confirmed')->where('check_in','<=',$date)->where('check_out','>',$date);
    }

    public function scopeOccupancy(Builder $query, string $state): Builder
    {
        return match ($state) {
            'occupied' => $query->where(fn ($q) => $q->whereHas('currentLeases')
                ->orWhere(fn ($q) => $q->where('status','occupied')->whereDoesntHave('approvedLeases'))),
            'available' => $query->where(fn ($q) => $q->where('status','available')
                ->orWhere(fn ($q) => $q->where('status','occupied')->whereHas('approvedLeases')))
                ->whereDoesntHave('currentLeases')->whereDoesntHave('currentReservations'),
            'reserved' => $query->whereIn('status',['available','occupied'])->whereHas('currentReservations')
                ->whereDoesntHave('currentLeases')->where(fn ($q) => $q->where('status','available')->orWhereHas('approvedLeases')),
            'inactive','maintenance' => $query->where('status',$state)->whereDoesntHave('currentLeases'),
            default => $query,
        };
    }

    public function scopeWithOccupancy(Builder $query): Builder
    {
        return $query->withExists(['currentLeases','approvedLeases','currentReservations']);
    }

    public function getOccupancyStatusAttribute(): string
    {
        $flag = fn ($name) => $this->getAttributeValue(\Illuminate\Support\Str::snake($name).'_exists') ?? $this->{$name}()->exists();
        if ($flag('currentLeases')) return 'occupied';
        if (in_array($this->status,['maintenance','inactive'],true)) return $this->status;
        if ($this->status === 'occupied' && ! $flag('approvedLeases')) return 'occupied';
        return $flag('currentReservations') ? 'reserved' : 'available';
    }

    public static function statusLabels(): array
    {
        return ['available'=>'متاحة','occupied'=>'مؤجرة','reserved'=>'محجوزة','maintenance'=>'صيانة','inactive'=>'غير نشطة'];
    }

    public static function rentPeriodLabels(): array
    {
        return ['daily'=>'يوميًا','monthly'=>'شهريًا','yearly'=>'سنويًا'];
    }

    public function getRentPeriodLabelAttribute(): string
    {
        return self::rentPeriodLabels()[$this->rent_period] ?? self::rentPeriodLabels()['monthly'];
    }
}
