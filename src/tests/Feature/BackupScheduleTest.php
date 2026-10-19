<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BackupScheduleTest extends TestCase
{
    use RefreshDatabase;

    public function test_nightly_backup_is_scheduled(): void
    {
        $events = collect(app(Schedule::class)->events())
            ->filter(fn ($e) => str_contains($e->command, 'scripts/backup.sh'));

        $this->assertCount(1, $events);
        $this->assertSame('0 3 * * *', $events->first()->expression);
        $this->assertTrue($events->first()->withoutOverlapping);
    }

    public function test_backup_and_restore_scripts_exist_and_are_valid_bash(): void
    {
        foreach (['backup.sh', 'restore.sh'] as $script) {
            $path = base_path('scripts/'.$script);
            $this->assertFileExists($path);
            exec('bash -n '.escapeshellarg($path).' 2>&1', $output, $code);
            $this->assertSame(0, $code, "{$script} has a syntax error: ".implode("\n", $output));
        }
    }

    public function test_create_owner_command(): void
    {
        $this->artisan('recipes:create-owner', ['--name' => 'Sarah', '--email' => 'sarah@example.test', '--password' => 'longenough'])
            ->expectsOutputToContain('Created owner')
            ->assertSuccessful();

        $user = User::firstWhere('email', 'sarah@example.test');
        $this->assertNotNull($user);
        $this->assertTrue(\Hash::check('longenough', $user->password));

        $this->artisan('recipes:create-owner', ['--name' => 'Sarah', '--email' => 'sarah@example.test', '--password' => 'anotherpass'])
            ->expectsOutputToContain('Updated owner')
            ->assertSuccessful();
        $this->assertSame(1, User::count());

        $this->artisan('recipes:create-owner', ['--name' => 'S', '--email' => 'x@example.test', '--password' => 'short'])
            ->assertFailed();
    }
}
