<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;

use function Laravel\Prompts\password;
use function Laravel\Prompts\text;

class CreateOwner extends Command
{
    protected $signature = 'recipes:create-owner
                            {--name= : Display name (default OWNER_NAME)}
                            {--email= : Login email (default OWNER_EMAIL)}
                            {--password= : Password (default OWNER_PASSWORD, else prompted)}';

    protected $description = 'Create or update the single owner account (registration is disabled)';

    public function handle(): int
    {
        $name = $this->option('name') ?: env('OWNER_NAME') ?: text('Name', default: 'Sarah');
        $email = $this->option('email') ?: env('OWNER_EMAIL') ?: text('Email', required: true);
        $pass = $this->option('password') ?: env('OWNER_PASSWORD') ?: password('Password (min 8 characters)', required: true);

        if (strlen($pass) < 8) {
            $this->error('Password must be at least 8 characters.');

            return self::FAILURE;
        }

        $existed = User::where('email', $email)->exists();
        User::updateOrCreate(
            ['email' => $email],
            ['name' => $name, 'password' => Hash::make($pass), 'email_verified_at' => now()],
        );

        $this->info(($existed ? 'Updated' : 'Created')." owner {$email}.");

        return self::SUCCESS;
    }
}
