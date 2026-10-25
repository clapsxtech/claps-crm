<?php
namespace Tests;

use App\Models\Master;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;

abstract class TestCase extends \Illuminate\Foundation\Testing\TestCase
{
    use CreatesApplication, RefreshDatabase;

    // Run migration commands directly; no console-output mocking is needed.
    public $mockConsoleOutput = false;

    protected function setUp(): void
    {
        parent::setUp();
        config(['erp.business_id' => 1, 'integrations.sheets.enabled' => false]);
        if (getenv('D1_COMPAT_TEST') === '1') {
            $remote = new \Tests\Support\SqliteD1Gateway(\Illuminate\Support\Facades\DB::connection()->getPdo());
            $path = sys_get_temp_dir().'/claps-d1-suite-'.bin2hex(random_bytes(10));
            $this->app['db']->extend('d1_compat', function ($config, $name) use ($remote, $path) {
                return new \App\Infrastructure\Cloudflare\D1Connection(new \App\Infrastructure\Cloudflare\D1Pdo($remote, $path), $path, '', ['driver'=>'sqlite','name'=>$name]);
            });
            config(['database.connections.d1_compat'=>['driver'=>'d1_compat']]);
            $this->app['db']->setDefaultConnection('d1_compat');
            $this->beforeApplicationDestroyed(function () use ($path) {
                foreach (glob($path.'*') as $file) if (is_file($file)) unlink($file);
            });
        }
    }

    protected function tearDown(): void
    {
        if (getenv('D1_COMPAT_TEST') === '1' && $this->app) {
            // RefreshDatabase owns the original SQLite fixture transaction.
            $this->app['db']->setDefaultConnection('sqlite');
        }
        parent::tearDown();
    }

    protected function createAdmin(array $attributes = []): User
    {
        return $this->createMember(array_merge(['id' => 1, 'name' => 'QA Admin', 'email' => 'admin@example.invalid'], $attributes));
    }

    protected function createMember(array $attributes = []): User
    {
        $id = $attributes['id'] ?? max(2, ((int) User::max('id')) + 1);
        return User::unguarded(fn () => User::create(array_merge([
            'id' => $id, 'business_id' => 1, 'name' => 'QA Staff',
            'email' => "staff{$id}@example.invalid", 'phone' => sprintf('900%07d', $id),
            'password' => Hash::make('Local-Testing-Only-2026'), 'gender' => 'OTHER',
            'profile_photo' => 'img/user2.png', 'status' => 1,
        ], $attributes)));
    }

    protected function createMaster(array $attributes = []): Master
    {
        return Master::create(array_merge([
            'teacher_name' => 'QA Teacher', 'teacher_code' => 'T-QA',
            'teacher_dob' => '01/01/1990', 'student_name' => 'QA Student', 'student_code' => 'S-QA',
            'hour_payment' => '100.00', 'payment_per_hour' => '80.00', 'profit_per_hour' => '20.00',
            'team' => 'QA',
        ], $attributes));
    }

    protected function createSubject(array $attributes = []): Subject
    {
        return Subject::create(array_merge(['name' => 'QA Mathematics'], $attributes));
    }
}
