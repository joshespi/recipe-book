<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;

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
        $name = $this->option('name') ?: config('recipes.owner.name') ?: text('Name', default: 'Sarah');
        $email = $this->option('email') ?: config('recipes.owner.email') ?: text('Email', required: true);
        $pass = $this->option('password') ?: config('recipes.owner.password') ?: password('Password (min 8 characters)', required: true);

        if (strlen($pass) < 8) {
            $this->error('Password must be at least 8 characters.');

            return self::FAILURE;
        }

        // The User model hashes the password itself (the 'hashed' cast).
        $user = User::updateOrCreate(['email' => $email], ['name' => $name, 'password' => $pass]);

        $this->info(($user->wasRecentlyCreated ? 'Created' : 'Updated')." owner {$email}.");

        return self::SUCCESS;
    }
}
