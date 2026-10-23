<?php

namespace App\Modules\Academics\Services;

use App\Modules\Integrations\Exceptions\SubmissionConflict;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

class ResultResponse
{
    public function submit(callable $action)
    {
        try {
            $result = $action();

            return response()->json(array_merge([
                'success' => true, 'msg' => 'Successfully created. Sheet synchronization is queued.',
                'next_submission_key' => (string) Str::uuid(),
            ], $result));
        } catch (ValidationException $exception) {
            return response()->json(['success' => false, 'msg' => collect($exception->errors())->flatten()->first(), 'errors' => $exception->errors()], 422);
        } catch (AuthorizationException $exception) {
            return response()->json(['success' => false, 'msg' => 'Teacher verification failed.'], 403);
        } catch (SubmissionConflict $exception) {
            return response()->json(['success' => false, 'msg' => $exception->getMessage()], 409);
        } catch (Throwable $exception) {
            // Never log request input or a database/SDK exception containing it.
            Log::error('Academic submission failed', ['exception_type' => get_class($exception)]);

            return response()->json(['success' => false, 'msg' => 'Unable to save the record. You can safely retry this submission.'], 500);
        }
    }
}
