<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;

/** Local CLI password recovery. Never prints passwords or stores them in shell history. */
final class AsabSetPassword extends Command
{
    protected $signature = 'asab:account-password {email}';
    protected $description = 'Securely set a password for an existing ASAB SOFT account from local CLI';

    public function handle(): int
    {
        $email = trim((string) $this->argument('email'));
        $user = User::where('email', $email)->first();
        if (! $user) {
            $this->error('No account found with this email. Run the account seeder first.');
            return self::FAILURE;
        }
        $first = $this->secret('New password (12+ characters; hidden)');
        $second = $this->secret('Confirm new password (hidden)');
        if (! is_string($first) || mb_strlen($first) < 12 || ! hash_equals((string) $first, (string) $second)) {
            $this->error('Password is too short or the confirmation does not match.');
            return self::FAILURE;
        }
        $user->password = $first; // User password cast automatically hashes it.
        $user->save();
        $this->info('Account password updated successfully.');
        return self::SUCCESS;
    }
}
