<?php

namespace Database\Seeders;

use App\Models\{Company, FinancialAccount, User};
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Run explicitly: php artisan db:seed --class=AsabInitializeAccountsSeeder
 * No default passwords, no fictional users, and existing passwords are never overwritten.
 */
final class AsabInitializeAccountsSeeder extends Seeder
{
    public function run(): void
    {
        $platformEmail = trim((string) env('PLATFORM_OWNER_EMAIL', ''));
        $platformPassword = (string) env('PLATFORM_OWNER_PASSWORD', '');
        $companyEmail = trim((string) env('ASAB_COMPANY_ADMIN_EMAIL', ''));
        $companyPassword = (string) env('ASAB_COMPANY_ADMIN_PASSWORD', '');
        $companySlug = trim((string) env('ASAB_CLIENT_COMPANY_SLUG', 'asab-pilot-client'));
        $companyName = trim((string) env('ASAB_CLIENT_COMPANY_NAME', 'إدارة عقارات العميل التجريبي'));
        $companyCity = trim((string) env('ASAB_CLIENT_CITY', 'غير محدد'));

        $needsPlatformOwner = ! User::where('role', 'super_admin')->exists();
        if ($needsPlatformOwner && (! filter_var($platformEmail, FILTER_VALIDATE_EMAIL) || mb_strlen($platformPassword) < 12)) {
            throw new RuntimeException('Set PLATFORM_OWNER_EMAIL and PLATFORM_OWNER_PASSWORD (12+ chars) in .env.');
        }
        if (! filter_var($companyEmail, FILTER_VALIDATE_EMAIL) || mb_strlen($companyPassword) < 12) {
            throw new RuntimeException('Set ASAB_COMPANY_ADMIN_EMAIL and ASAB_COMPANY_ADMIN_PASSWORD (12+ chars) in .env.');
        }
        if ($platformEmail !== '' && strcasecmp($platformEmail, $companyEmail) === 0) {
            throw new RuntimeException('Platform owner and company admin must use different email addresses.');
        }
        if (! preg_match('/^[a-z0-9][a-z0-9-]{1,98}$/', $companySlug)) {
            throw new RuntimeException('Invalid ASAB_CLIENT_COMPANY_SLUG. Use lowercase letters, digits, and hyphens.');
        }
        if ($companyName === '' || mb_strlen($companyName) > 160) {
            throw new RuntimeException('Invalid ASAB_CLIENT_COMPANY_NAME.');
        }
        if ($companyCity === '' || mb_strlen($companyCity) > 100) {
            throw new RuntimeException('Invalid ASAB_CLIENT_CITY.');
        }

        DB::transaction(function () use ($needsPlatformOwner, $platformEmail, $platformPassword, $companyEmail, $companyPassword, $companySlug, $companyName, $companyCity): void {
            if ($needsPlatformOwner) {
                if (User::where('email', $platformEmail)->exists()) {
                    throw new RuntimeException('PLATFORM_OWNER_EMAIL belongs to an existing account. Choose a different email.');
                }
                User::create([
                    'company_id' => null,
                    'name' => env('PLATFORM_OWNER_NAME', 'مالك منصة عصب سوفت'),
                    'email' => $platformEmail,
                    'password' => $platformPassword,
                    'role' => 'super_admin',
                    'is_active' => true,
                ]);
            }

            $company = Company::firstOrCreate(['slug' => $companySlug], [
                'name' => $companyName, 'country_code' => 'SA', 'currency' => 'SAR',
                'city' => $companyCity, 'is_active' => true,
            ]);
            if ($company->country_code !== 'SA' || $company->currency !== 'SAR') {
                throw new RuntimeException('The existing company slug is not configured for Saudi Arabia / SAR.');
            }
            $existing = User::where('email', $companyEmail)->first();
            if ($existing && ((int) $existing->company_id !== (int) $company->id || $existing->role !== 'company_admin')) {
                throw new RuntimeException('ASAB_COMPANY_ADMIN_EMAIL is already used by another role or company.');
            }
            if (! $existing) {
                User::create([
                    'company_id' => $company->id,
                    'name' => env('ASAB_COMPANY_ADMIN_NAME', 'مدير شركة العميل'),
                    'email' => $companyEmail,
                    'password' => $companyPassword,
                    'role' => 'company_admin',
                    'is_active' => true,
                ]);
            }
            foreach (['الصندوق الرئيسي' => 'cash', 'الحساب البنكي الرئيسي' => 'bank'] as $name => $type) {
                FinancialAccount::firstOrCreate(['company_id' => $company->id, 'name' => $name], [
                    'type' => $type, 'currency' => 'SAR', 'opening_balance' => 0, 'is_active' => true,
                ]);
            }
        });
        $this->command?->info('ASAB accounts and company are ready. Existing passwords were not changed.');
    }
}
