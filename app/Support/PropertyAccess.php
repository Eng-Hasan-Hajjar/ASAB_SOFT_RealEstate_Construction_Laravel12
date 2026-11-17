<?php
namespace App\Support;

use App\Models\PropertyAccessGrant;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Central, server-side authority for the per-employee property/price permission system.
 *
 * Every controller that lists, shows, edits or exports properties or units must go through
 * this class rather than re-implementing the "is this user restricted, and to what" logic —
 * that is what keeps the rule enforced everywhere instead of only in the UI.
 *
 * Model: a user is either unrestricted (sees everything their role already allows — this is
 * the default and exactly matches pre-existing behaviour) or, when `scope_properties` is on,
 * restricted to the properties listed in property_access_grants, with independent per-property
 * flags for viewing the property, viewing its sale price, viewing its rent price, and editing
 * either price. company_admin, manager and the platform owner are never restricted, whatever
 * the flag says — they are the people configuring everyone else's access.
 */
final class PropertyAccess
{
    // Cached per-container-instance rather than in a plain PHP static array: a static array
    // survives for the whole PHP process, which is wrong wherever more than one request shares
    // a process (the test suite included — PHPUnit reuses one process across every test, and a
    // long-running worker such as Octane would too). Binding it on the current application
    // instance means it is naturally thrown away whenever a fresh request/container is built,
    // and can still be invalidated explicitly within one request via forgetCache().
    private const CACHE_KEY = 'property-access.grants-cache';

    public static function restricted(User $user): bool
    {
        return $user->isPropertyScoped();
    }

    /** @return Collection<int,PropertyAccessGrant> this user's grants, keyed by property_id */
    public static function grants(User $user): Collection
    {
        $cache = app()->bound(self::CACHE_KEY) ? app(self::CACHE_KEY) : [];
        if (!isset($cache[$user->id])) {
            $cache[$user->id] = PropertyAccessGrant::where('user_id', $user->id)->get()->keyBy('property_id');
            app()->instance(self::CACHE_KEY, $cache);
        }
        return $cache[$user->id];
    }

    /** Call after a grants update so the same request doesn't keep reading stale data. */
    public static function forgetCache(?int $userId = null): void
    {
        if (!app()->bound(self::CACHE_KEY)) return;
        $cache = app(self::CACHE_KEY);
        if ($userId === null) { app()->instance(self::CACHE_KEY, []); return; }
        unset($cache[$userId]);
        app()->instance(self::CACHE_KEY, $cache);
    }

    /** @return int[] property ids this user may view. Empty for an unrestricted user is meaningless — check restricted() first. */
    public static function grantedPropertyIds(User $user): array
    {
        if (!self::restricted($user)) return [];
        return self::grants($user)->filter(fn (PropertyAccessGrant $g) => $g->can_view_property)->keys()->all();
    }

    // NOTE: unrestricted-first ordering matters here. An unrestricted user (the default — see
    // the class docblock) has full access regardless of $propertyId, including when it's null
    // (e.g. the "add unit" form before a property has been chosen yet). Only a *restricted*
    // user needs an actual, known property to be checked against their grants; for them, a
    // null/unknown property id correctly means "not allowed" (checked after the early-return).
    public static function canViewProperty(User $user, ?int $propertyId): bool
    {
        if (!self::restricted($user)) return true;
        if ($propertyId === null) return false;
        $grant = self::grants($user)->get($propertyId);
        return (bool) ($grant?->can_view_property);
    }

    /** @param string $type 'sale' or 'rent' */
    public static function canViewPrice(User $user, ?int $propertyId, string $type): bool
    {
        if (!self::restricted($user)) return true;
        if (!self::canViewProperty($user, $propertyId)) return false;
        $grant = self::grants($user)->get($propertyId);
        if (!$grant) return false;
        return $type === 'sale' ? (bool) $grant->can_view_sale_price : (bool) $grant->can_view_rent_price;
    }

    public static function canEditPrices(User $user, ?int $propertyId): bool
    {
        if (!self::restricted($user)) return true;
        if (!self::canViewProperty($user, $propertyId)) return false;
        $grant = self::grants($user)->get($propertyId);
        return (bool) ($grant?->can_edit_prices);
    }

    /** Restrict a Property query (column `id`) to what this user may view. No-op when unrestricted. */
    public static function scopeProperties(Builder $query, User $user): Builder
    {
        if (self::restricted($user)) {
            $query->whereIn('id', self::grantedPropertyIds($user));
        }
        return $query;
    }

    /** Restrict a query with a `property_id` column (units and the like) to what this user may view. */
    public static function scopeByPropertyId(Builder $query, User $user, string $column = 'property_id'): Builder
    {
        if (self::restricted($user)) {
            $query->whereIn($column, self::grantedPropertyIds($user));
        }
        return $query;
    }

    /** A short, safe, display-ready label — never reveals the amount when the viewer may not see it. */
    public static function priceLabel(User $user, ?int $propertyId, string $type, $amount, $company): string
    {
        if (!self::canViewPrice($user, $propertyId, $type)) return '🔒 غير مصرح بعرض السعر';
        return $amount === null ? '—' : $company->money($amount);
    }
}
