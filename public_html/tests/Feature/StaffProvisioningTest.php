<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\People\Services\StaffProvisioner;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Mockery;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class StaffProvisioningTest extends TestCase
{
    private function payload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'New Staff', 'email' => 'new-staff@example.invalid',
            'phone' => '9123456789', 'gender' => 'Female',
            'password' => 'Staff-Testing-Only-2026', 'confirm_password' => 'Staff-Testing-Only-2026',
        ], $overrides);
    }

    public function test_creation_works_against_the_bundled_schema_and_current_form_fields(): void
    {
        $this->actingAs($this->createAdmin())->postJson(route('users.store'), $this->payload([
            'dob' => '1990-01-02', 'position' => 'Teacher', 'language' => 'English',
            'custom_fields' => 'Test metadata', 'is_commission_agent' => '1',
            'commission_type' => 'sale_amount', 'blood_group' => 'O+',
            'alternate_number' => '9234567890', 'emergency_contact_number' => '9345678901',
            'service_expiry' => '2030-12-31', 'permanent_address' => '123 Test Road',
            'current_address' => '456 Test Road',
            'bank_details' => ['account_holder_name' => 'Test Staff', 'account_number' => 'TEST-ONLY',
                'bank_name' => 'Test Bank', 'bank_code' => 'TEST', 'branch' => 'Test Branch', 'tax_payer_id' => 'TEST-ID'],
        ]))->assertOk()->assertJson(['success' => true]);

        $user = User::where('email', 'new-staff@example.invalid')->firstOrFail();
        $this->assertSame('FEMALE', $user->gender);
        $this->assertSame(1, (int) $user->business_id);
        $this->assertSame('user', $user->user_type);
        $this->assertSame('Teacher', $user->position);
        $this->assertSame('1990-01-02', $user->dob);
        $this->assertSame('English', $user->language);
        $this->assertSame('2030-12-31', $user->service_expiry);
        $this->assertSame('Test metadata', $user->custom_fields);
        $this->assertSame('sale_amount', $user->commission_type);
        $this->assertSame('O+', $user->blood_group);
        $this->assertSame('9234567890', $user->alternate_number);
        $this->assertSame('9345678901', $user->emergency_contact_number);
        $this->assertSame('123 Test Road', json_decode($user->address, true)['permanent_address']);
        $this->assertSame('TEST-ONLY', json_decode($user->bank_details, true)['account_number']);
        $this->assertTrue(Hash::check('Staff-Testing-Only-2026', $user->password));
        $this->assertFalse(Schema::hasColumn('users', 'employee_code'));
        $this->assertCount(0, $user->roles);
    }

    public function test_create_requires_a_nonempty_confirmed_strong_password(): void
    {
        $this->actingAs($this->createAdmin());
        foreach ([
            ['password' => null, 'confirm_password' => null],
            ['password' => 'short', 'confirm_password' => 'short'],
            ['password' => 'Staff-Testing-Only-2026', 'confirm_password' => null],
            ['password' => 'Staff-Testing-Only-2026', 'confirm_password' => 'Different-Password'],
            ['password' => str_repeat('é', 40), 'confirm_password' => str_repeat('é', 40)],
        ] as $password) {
            $this->postJson(route('users.store'), $this->payload($password))
                ->assertStatus(422)->assertJson(['success' => false])->assertJsonValidationErrors('password');
        }
        $this->assertSame(1, User::count());
    }

    public function test_legacy_gender_values_are_normalized_to_the_enum(): void
    {
        $this->actingAs($this->createAdmin())->postJson(route('users.store'), $this->payload(['gender' => 'Others']))
            ->assertOk()->assertJson(['success' => true]);
        $this->assertDatabaseHas('users', ['email' => 'new-staff@example.invalid', 'gender' => 'OTHER']);
    }

    public function test_all_optional_profile_inputs_are_validated_without_writing(): void
    {
        $this->actingAs($this->createAdmin());
        $invalid = [
            'dob' => 'not-a-date', 'service_expiry' => 'not-a-date', 'gender' => 'INVALID',
            'position' => ['invalid'], 'language' => ['invalid'], 'custom_fields' => ['invalid'],
            'is_commission_agent' => 'yes', 'commission_type' => 'invalid', 'blood_group' => 'invalid',
            'alternate_number' => '123', 'emergency_contact_number' => '123',
            'permanent_address' => ['invalid'], 'current_address' => ['invalid'],
            'bank_details' => ['account_number' => ['invalid']],
        ];
        foreach ($invalid as $field => $value) {
            $this->postJson(route('users.store'), $this->payload([$field => $value]))
                ->assertStatus(422)->assertJson(['success' => false]);
        }
        $this->postJson(route('users.store'), $this->payload([
            'permanent_address' => str_repeat('a', 150), 'current_address' => str_repeat('b', 150),
        ]))->assertStatus(422)->assertJsonValidationErrors('permanent_address');
        $this->assertSame(1, User::count());
    }

    public function test_privilege_and_organization_fields_are_rejected(): void
    {
        $this->actingAs($this->createAdmin());
        foreach (['business_id' => 2, 'user_type' => 'admin', 'role' => 'Admin', 'roles' => ['Admin'],
            'permissions' => ['administration'], 'status' => 0, 'employee_code' => '1', 'id' => 1] as $field => $value) {
            $this->postJson(route('users.store'), $this->payload([$field => $value]))
                ->assertStatus(422)->assertJsonValidationErrors($field);
        }
        $this->assertSame(1, User::count());
    }

    public function test_edits_preserve_privileges_business_password_and_absent_optional_metadata(): void
    {
        $admin = $this->createAdmin();
        $user = $this->createMember(['user_type' => 'admin', 'position' => 'Existing Position', 'bank_details' => '{"account_number":"TEST-EXISTING"}']);
        $role = Role::create(['name' => 'Existing Role', 'guard_name' => 'web']);
        $user->assignRole($role);
        $hash = $user->password;
        $this->actingAs($admin)->putJson(route('users.update', $user), $this->payload([
            'email' => $user->email, 'phone' => $user->phone, 'password' => null, 'confirm_password' => null,
        ]))->assertOk()->assertJson(['success' => true]);
        $user->refresh();
        $this->assertSame('admin', $user->user_type);
        $this->assertSame(1, (int) $user->business_id);
        $this->assertSame($hash, $user->password);
        $this->assertSame('Existing Position', $user->position);
        $this->assertSame('{"account_number":"TEST-EXISTING"}', $user->bank_details);
        $this->assertTrue($user->hasRole('Existing Role'));
    }

    public function test_privilege_fields_are_also_rejected_on_updates_without_mutation(): void
    {
        $admin = $this->createAdmin();
        $user = $this->createMember(['user_type' => 'admin']);
        $before = $user->fresh()->getAttributes();
        $this->actingAs($admin)->putJson(route('users.update', $user), $this->payload([
            'business_id' => 2, 'user_type' => 'user', 'roles' => ['Admin'], 'permissions' => ['administration'],
        ]))->assertStatus(422)->assertJsonValidationErrors(['business_id', 'user_type', 'roles', 'permissions']);
        $this->assertSame($before, $user->fresh()->getAttributes());
    }

    public function test_edits_can_change_password_only_with_matching_confirmation(): void
    {
        $admin = $this->createAdmin();
        $user = $this->createMember();
        $this->actingAs($admin)->putJson(route('users.update', $user), $this->payload(['confirm_password' => 'Wrong-Confirmation']))
            ->assertStatus(422)->assertJsonValidationErrors('password');
        $this->putJson(route('users.update', $user), $this->payload())
            ->assertOk()->assertJson(['success' => true]);
        $this->assertTrue(Hash::check('Staff-Testing-Only-2026', $user->fresh()->password));
    }

    public function test_cross_organization_staff_are_never_listed_read_or_edited(): void
    {
        $admin = $this->createAdmin();
        $foreign = $this->createMember(['business_id' => 2, 'name' => 'Foreign Staff']);
        $before = $foreign->fresh()->getAttributes();
        $this->actingAs($admin)->getJson(route('users.index'), ['X-Requested-With' => 'XMLHttpRequest'])
            ->assertOk()->assertJsonCount(1, 'data')->assertDontSee('Foreign Staff');
        $this->get(route('users.edit', $foreign))->assertNotFound();
        $this->putJson(route('users.update', $foreign), $this->payload())->assertNotFound();
        $this->assertSame($before, $foreign->fresh()->getAttributes());
    }

    public function test_nonadministrators_and_foreign_administrators_cannot_provision_staff(): void
    {
        $member = $this->createMember();
        $this->actingAs($member)->postJson(route('users.store'), $this->payload())->assertForbidden();
        $foreignAdmin = $this->createMember(['business_id' => 2, 'user_type' => 'admin']);
        $this->actingAs($foreignAdmin)->postJson(route('users.store'), $this->payload())->assertForbidden();
        $this->assertSame(2, User::count());
    }

    public function test_create_edit_and_profile_forms_render_with_null_legacy_metadata(): void
    {
        $admin = $this->createAdmin(['address' => null, 'bank_details' => null, 'gender' => 'OTHER']);
        $this->actingAs($admin)->get(route('users.create'))->assertOk()->assertSee('multipart/form-data', false);
        $this->get(route('users.edit', $admin))->assertOk()->assertSee('value="OTHER"  selected', false);
        $this->get(route('profile'))->assertOk();
        $this->get(route('users.index'))->assertOk()->assertSee(route('users.create'), false);
    }

    public function test_invalid_files_are_rejected(): void
    {
        $this->actingAs($this->createAdmin())->postJson(route('users.store'), $this->payload([
            'profile_photo' => UploadedFile::fake()->createWithContent('profile.php', '<?php echo "bad";'),
        ]))->assertStatus(422)->assertJsonValidationErrors('profile_photo');
        $this->assertSame(1, User::count());
    }

    public function test_photo_replacement_cannot_delete_an_arbitrary_legacy_path(): void
    {
        $directory = storage_path('framework/testing/staff-public-'.uniqid());
        File::ensureDirectoryExists($directory);
        $this->app->usePublicPath($directory);
        $sentinel = $directory.'/do-not-delete.txt';
        File::put($sentinel, 'Keep this file.');
        try {
            $admin = $this->createAdmin();
            $user = $this->createMember(['profile_photo' => '/do-not-delete.txt']);
            $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+a1uoAAAAASUVORK5CYII=');
            $this->actingAs($admin)->putJson(route('users.update', $user), $this->payload([
                'profile_photo' => UploadedFile::fake()->createWithContent('photo.png', $png),
            ]))->assertOk()->assertJson(['success' => true]);
            $this->assertFileExists($sentinel);
            $this->assertMatchesRegularExpression('#^/uploads/profile_photo/[a-f0-9-]{36}\.png$#', $user->fresh()->profile_photo);
            $this->assertFileExists($directory.$user->fresh()->profile_photo);
        } finally {
            File::deleteDirectory($directory);
        }
    }

    public function test_failed_saves_remove_only_the_new_upload_and_leave_no_partial_account(): void
    {
        $directory = storage_path('framework/testing/staff-public-'.uniqid());
        File::ensureDirectoryExists($directory);
        $this->app->usePublicPath($directory);
        $admin = $this->createAdmin();
        $dispatcher = User::getEventDispatcher();
        User::setEventDispatcher(new \Illuminate\Events\Dispatcher($this->app));
        User::saving(function () { throw new \RuntimeException('Simulated database failure.'); });
        try {
            $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+a1uoAAAAASUVORK5CYII=');
            $this->actingAs($admin)->postJson(route('users.store'), $this->payload([
                'profile_photo' => UploadedFile::fake()->createWithContent('photo.png', $png),
            ]))->assertStatus(500)->assertJson(['success' => false]);
            $this->assertSame(1, User::count());
            $this->assertCount(0, File::allFiles($directory));
        } finally {
            User::setEventDispatcher($dispatcher);
            File::deleteDirectory($directory);
        }
    }

    public function test_duplicates_and_deleted_targets_cannot_be_used_for_provisioning(): void
    {
        $admin = $this->createAdmin();
        $user = $this->createMember();
        $this->actingAs($admin)->postJson(route('users.store'), $this->payload(['email' => $user->email, 'phone' => $user->phone]))
            ->assertStatus(422)->assertJsonValidationErrors(['email', 'phone']);
        $deleted = $this->createMember(['deleted_at' => now()]);
        $this->get(route('users.edit', $deleted))->assertNotFound();
        $this->putJson(route('users.update', $deleted), $this->payload())->assertNotFound();
    }

    public function test_persistence_failures_return_generic_errors_without_logging_payloads(): void
    {
        $admin = $this->createAdmin();
        $service = Mockery::mock(StaffProvisioner::class)->makePartial();
        $service->shouldReceive('staffQuery')->once()->andReturn(User::query());
        $service->shouldReceive('create')->once()->andThrow(new \RuntimeException('Sensitive request payload must not be logged.'));
        $this->app->instance(StaffProvisioner::class, $service);
        Log::shouldReceive('error')->once()->with('Unable to create a staff profile.');
        $this->actingAs($admin)->postJson(route('users.store'), $this->payload())
            ->assertStatus(500)->assertExactJson(['success' => false, 'msg' => 'Unable to create the user.']);
    }

    public function test_additive_migration_can_reapply_and_rollback_without_removing_preexisting_columns(): void
    {
        require_once database_path('migrations/2026_10_23_110000_add_staff_profile_columns_to_users.php');
        $user = $this->createAdmin(['position' => 'Keep existing data']);
        $migration = new \AddStaffProfileColumnsToUsers();
        $migration->up();
        $migration->down();
        $this->assertTrue(Schema::hasColumns('users', ['position', 'bank_details', 'user_type']));
        $this->assertSame('Keep existing data', $user->fresh()->position);
    }
}
