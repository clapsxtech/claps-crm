<?php

namespace App\Modules\People\Services;

use App\Models\User;
use App\Modules\IdentityAccess\Services\SiteAccessPolicy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/** Administrative staff profiles only. Roles, permissions and organization ownership are never writable here. */
class StaffProvisioner
{
    private const PROFILE_FIELDS = [
        'name', 'email', 'phone', 'dob', 'gender', 'position', 'language',
        'custom_fields', 'is_commission_agent', 'commission_type', 'blood_group',
        'alternate_number', 'emergency_contact_number', 'service_expiry',
    ];

    private const BANK_FIELDS = [
        'account_holder_name', 'account_number', 'bank_name', 'bank_code', 'branch', 'tax_payer_id',
    ];

    private $access;

    public function __construct(SiteAccessPolicy $access)
    {
        $this->access = $access;
    }

    public function staffQuery(User $actor): Builder
    {
        $this->access->assertAdministrator($actor);

        return User::query()->where('business_id', (int) config('erp.business_id', 1))
            ->whereNull('deleted_at');
    }

    public function create(User $actor, array $input): User
    {
        $this->access->assertAdministrator($actor);
        $data = $this->validated($input);
        $user = new User();
        $user->business_id = (int) config('erp.business_id', 1);
        $user->user_type = 'user';
        $user->profile_photo = '';
        $user->is_commission_agent = false;

        return $this->persist($user, $data);
    }

    public function update(User $actor, $id, array $input): User
    {
        // Resolve through the organization boundary before validating or writing anything.
        $user = $this->staffQuery($actor)->findOrFail($id);

        return $this->persist($user, $this->validated($input, $user));
    }

    private function validated(array $input, ?User $user = null): array
    {
        if (isset($input['gender']) && is_string($input['gender'])) {
            $input['gender'] = strtoupper(trim($input['gender']));
            if ($input['gender'] === 'OTHERS') {
                $input['gender'] = 'OTHER';
            }
        }
        $email = Rule::unique('users', 'email');
        $phone = Rule::unique('users', 'phone');
        if ($user) {
            $email->ignore($user->getKey());
            $phone->ignore($user->getKey());
        }
        $rules = [
            'name' => ['required', 'string', 'min:3', 'max:100'],
            'email' => ['required', 'string', 'email', 'max:255', $email],
            'phone' => ['required', 'string', 'regex:/^[0-9]{10}$/', $phone],
            'gender' => ['required', Rule::in(['MALE', 'FEMALE', 'OTHER'])],
            'password' => [$user ? 'nullable' : 'required', 'required_with:confirm_password', 'string', 'min:8', 'max:72', 'same:confirm_password'],
            'confirm_password' => [$user ? 'nullable' : 'required', 'required_with:password', 'string', 'max:72'],
            'dob' => ['nullable', 'date_format:Y-m-d', 'before_or_equal:today'],
            'service_expiry' => ['nullable', 'date_format:Y-m-d'],
            'position' => ['nullable', 'string', 'max:100'],
            'language' => ['nullable', 'string', 'max:100'],
            'custom_fields' => ['nullable', 'string', 'max:1000'],
            'is_commission_agent' => ['sometimes', 'boolean'],
            'commission_type' => ['nullable', Rule::in(['sale_amount', 'profit_amount'])],
            'blood_group' => ['nullable', Rule::in(['A+', 'A-', 'B+', 'B-', 'O+', 'O-', 'AB+', 'AB-'])],
            'alternate_number' => ['nullable', 'string', 'regex:/^[0-9]{10}$/'],
            'emergency_contact_number' => ['nullable', 'string', 'regex:/^[0-9]{10}$/'],
            'permanent_address' => ['nullable', 'string', 'max:200'],
            'current_address' => ['nullable', 'string', 'max:200'],
            'bank_details' => ['nullable', 'array:'.implode(',', self::BANK_FIELDS)],
            'profile_photo' => ['nullable', 'image', 'mimes:jpeg,jpg,png,gif,webp', 'max:2048'],
        ];
        foreach (self::BANK_FIELDS as $field) {
            $rules['bank_details.'.$field] = ['nullable', 'string', 'max:100'];
        }
        foreach (['id', 'business_id', 'user_type', 'role', 'roles', 'permissions', 'status', 'deleted_at', 'employee_code'] as $field) {
            $rules[$field] = ['prohibited'];
        }
        $validator = Validator::make($input, $rules);
        $validator->after(function ($validator) use ($input, $user) {
            if (isset($input['password']) && is_string($input['password']) && strlen($input['password']) > 72) {
                $validator->errors()->add('password', 'The password must not exceed 72 bytes.');
            }
            if (!$validator->errors()->has('permanent_address') && !$validator->errors()->has('current_address')
                && (array_key_exists('permanent_address', $input) || array_key_exists('current_address', $input))) {
                // The original bundled schema stores this JSON in a 255-character string.
                if (mb_strlen($this->address($input, $user)) > 255) {
                    $validator->errors()->add('permanent_address', 'The combined addresses are too long. Please use shorter addresses.');
                }
            }
        });

        return $validator->validate();
    }

    private function address(array $data, ?User $user): string
    {
        $existing = $user ? json_decode((string) $user->address, true) : [];
        $existing = is_array($existing) ? $existing : [];
        $address = [];
        foreach (['permanent_address', 'current_address'] as $field) {
            $address[$field] = array_key_exists($field, $data) ? $data[$field] : ($existing[$field] ?? null);
        }

        return json_encode($address, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
    }

    private function persist(User $user, array $data): User
    {
        $oldPhoto = $user->profile_photo;
        $newPhoto = null;
        try {
            if (($data['profile_photo'] ?? null) instanceof UploadedFile) {
                $directory = public_path('uploads/profile_photo');
                File::ensureDirectoryExists($directory, 0755, true);
                $filename = Str::uuid().'.'.$data['profile_photo']->extension();
                $data['profile_photo']->move($directory, $filename);
                $newPhoto = '/uploads/profile_photo/'.$filename;
            }
            DB::transaction(function () use ($user, $data, $newPhoto) {
                foreach (self::PROFILE_FIELDS as $field) {
                    if (array_key_exists($field, $data)) {
                        $user->setAttribute($field, $data[$field]);
                    }
                }
                if (array_key_exists('permanent_address', $data) || array_key_exists('current_address', $data)) {
                    $user->address = $this->address($data, $user);
                }
                if (array_key_exists('bank_details', $data)) {
                    $bank = array_intersect_key($data['bank_details'] ?? [], array_flip(self::BANK_FIELDS));
                    $user->bank_details = json_encode($bank, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
                }
                if (!empty($data['password'])) {
                    $user->password = Hash::make($data['password']);
                }
                if ($newPhoto !== null) {
                    $user->profile_photo = $newPhoto;
                }
                $user->save();
            });
        } catch (\Throwable $exception) {
            $this->deleteManagedPhoto($newPhoto);
            throw $exception;
        }
        if ($newPhoto !== null && $newPhoto !== $oldPhoto) {
            $this->deleteManagedPhoto($oldPhoto);
        }

        return $user;
    }

    private function deleteManagedPhoto(?string $path): void
    {
        // Legacy database paths are untrusted. Only remove generated files inside this upload directory.
        if (!$path || !preg_match('#^/uploads/profile_photo/[a-f0-9-]{36}\.(jpe?g|png|gif|webp)$#D', $path)) {
            return;
        }
        $directory = realpath(public_path('uploads/profile_photo'));
        $file = realpath(public_path(ltrim($path, '/')));
        if ($directory !== false && $file !== false && dirname($file) === $directory && is_file($file)) {
            // Failure to clean up a previous image must not turn a committed save into a reported failure.
            @unlink($file);
        }
    }
}
