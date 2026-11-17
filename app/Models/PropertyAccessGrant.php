<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Per-employee, per-property permission row. Read it through App\Support\PropertyAccess
 * rather than querying it directly — that class also handles the "company_admin/manager
 * always have full access" and "scope_properties disabled => unrestricted" rules.
 */
class PropertyAccessGrant extends Model
{
    protected $table = 'property_access_grants';
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'can_view_property' => 'boolean',
            'can_view_sale_price' => 'boolean',
            'can_view_rent_price' => 'boolean',
            'can_edit_prices' => 'boolean',
        ];
    }

    public function user(): BelongsTo { return $this->belongsTo(User::class); }
    public function property(): BelongsTo { return $this->belongsTo(Property::class); }
    public function company(): BelongsTo { return $this->belongsTo(Company::class); }
}
