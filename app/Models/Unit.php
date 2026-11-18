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

    // The only raw values the `status` column is ever set to through the application's own
    // forms (see config/modules.php). Anything else — corrupted data, a historical import row,
    // a future status the UI doesn't know about yet — is "undetermined" rather than silently
    // guessed as available. Kept as a single source of truth for both the scope and the accessor.
    public static function knownRawStatuses(): array
    {
        return ['available','occupied','maintenance','inactive'];
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
            // A row whose raw status isn't one of the known values and that has no live lease
            // evidence either: we genuinely don't know its state, so it's filtered/counted on
            // its own instead of silently falling into "available".
            'unknown' => $query->whereNotIn('status', self::knownRawStatuses())->whereDoesntHave('currentLeases'),
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
        if (!in_array($this->status, self::knownRawStatuses(), true)) return 'unknown';
        return $flag('currentReservations') ? 'reserved' : 'available';
    }

    public static function statusLabels(): array
    {
        return ['available'=>'متاحة','occupied'=>'مؤجرة','reserved'=>'محجوزة','maintenance'=>'صيانة','inactive'=>'غير نشطة','unknown'=>'غير محددة'];
    }

    /** Icon key (explorer.icon partial) per occupancy state — so the card/legend/preview never rely on color alone. */
    public static function statusIcons(): array
    {
        return ['available'=>'status-available','occupied'=>'status-occupied','reserved'=>'status-reserved','maintenance'=>'status-maintenance','inactive'=>'status-inactive','unknown'=>'status-unknown'];
    }

    public static function rentPeriodLabels(): array
    {
        return ['daily'=>'يوميًا','monthly'=>'شهريًا','yearly'=>'سنويًا'];
    }

    public function getRentPeriodLabelAttribute(): string
    {
        return self::rentPeriodLabels()[$this->rent_period] ?? self::rentPeriodLabels()['monthly'];
    }

    /** Shared, human-readable floor label — used by the explorer grid, the floor filter and the status summary alike. */
    public static function floorLabel(?int $floor): string
    {
        if ($floor === null) return 'غير محدد';
        return $floor === 0 ? 'الطابق الأرضي' : 'الطابق '.$floor;
    }

    public function getFloorLabelAttribute(): string
    {
        return self::floorLabel($this->floor);
    }
}
