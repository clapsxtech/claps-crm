<?php

namespace App\Modules\People\Services;

use App\Models\Master;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Validator;

/**
 * Compatibility boundary for the existing teacher DOB credential.
 * A DOB is not a strong password. Replace this gate with account-based identity
 * before expanding access; never return or log the stored credential.
 */
class TeacherIdentityVerifier
{
    public function verifyTeacher(array $input): Master
    {
        $input = $this->normalizeCredential($input);
        Validator::make($input, [
            'teacher_name' => ['required', 'string', 'max:255'],
            'teacher_code' => ['sometimes', 'required', 'string', 'max:255'],
            'password' => ['required', 'string', 'max:255'],
        ])->validate();

        $query = Master::where('teacher_name', $input['teacher_name']);
        if (isset($input['teacher_code'])) {
            $query->where('teacher_code', $input['teacher_code']);
        }
        $candidates = $query->get()->filter(function (Master $master) use ($input) {
            return $master->teacher_name === $input['teacher_name']
                && is_string($master->teacher_code) && trim($master->teacher_code) !== ''
                && (!isset($input['teacher_code']) || $master->teacher_code === $input['teacher_code']);
        });

        // A name shared by different teachers is not enough to select an identity.
        if ($candidates->pluck('teacher_code')->uniqueStrict()->count() !== 1
            || $candidates->pluck('teacher_dob')->uniqueStrict()->count() !== 1) {
            $this->deny();
        }
        $teacher = $candidates->first(function (Master $master) use ($input) {
            return $this->matchesCredential($master->teacher_dob, $input['password']);
        });
        if (!$teacher) {
            $this->deny();
        }

        return $teacher->makeHidden('teacher_dob');
    }

    public function verifyPair(array $input): Master
    {
        $input = $this->normalizeCredential($input);
        Validator::make($input, [
            'teacher_name' => ['required', 'string', 'max:255'],
            'teacher_code' => ['required', 'string', 'max:255'],
            'student_name' => ['required', 'string', 'max:255'],
            'student_code' => ['required', 'string', 'max:255'],
            'password' => ['required', 'string', 'max:255'],
        ])->validate();

        // Every assignment for this teacher must agree on the credential.
        $this->verifyTeacher($input);
        $candidates = Master::where('teacher_name', $input['teacher_name'])
            ->where('teacher_code', $input['teacher_code'])
            ->where('student_name', $input['student_name'])
            ->where('student_code', $input['student_code'])
            ->get()->filter(function (Master $master) use ($input) {
                return $master->teacher_name === $input['teacher_name']
                    && $master->teacher_code === $input['teacher_code']
                    && $master->student_name === $input['student_name']
                    && $master->student_code === $input['student_code']
                    && $this->matchesCredential($master->teacher_dob, $input['password']);
            });
        $assignmentDetails = $candidates->map(function (Master $master) {
            return $master->only(['teacher_name', 'teacher_code', 'student_name', 'student_code',
                'teacher_dob', 'hour_payment', 'profit_per_hour', 'payment_per_hour', 'team']);
        });
        if ($candidates->isEmpty() || $assignmentDetails->uniqueStrict()->count() !== 1) {
            $this->deny();
        }

        return $candidates->first()->makeHidden('teacher_dob');
    }

    private function normalizeCredential(array $input): array
    {
        // Do not let an explicitly empty password fall back to another value.
        if (!array_key_exists('password', $input)) {
            $input['password'] = $input['dob'] ?? $input['teacher_dob'] ?? null;
        }
        return $input;
    }

    private function matchesCredential($stored, string $provided): bool
    {
        return is_string($stored) && trim($stored) !== ''
            && trim($provided) !== '' && hash_equals($stored, $provided);
    }

    private function deny(): void
    {
        throw new AuthorizationException('Unable to verify the supplied identity.');
    }
}
