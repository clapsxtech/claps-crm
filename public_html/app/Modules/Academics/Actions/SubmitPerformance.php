<?php

namespace App\Modules\Academics\Actions;

use App\Models\Performance;
use App\Models\Topic;
use App\Modules\Academics\Services\ResultSheetSerializer;
use App\Modules\Integrations\Services\SubmissionWriter;
use App\Modules\People\Services\TeacherIdentityVerifier;
use Illuminate\Support\Facades\Validator;

class SubmitPerformance
{
    private $identity;
    private $writer;
    private $serializer;

    public function __construct(TeacherIdentityVerifier $identity, SubmissionWriter $writer, ResultSheetSerializer $serializer)
    {
        $this->identity = $identity;
        $this->writer = $writer;
        $this->serializer = $serializer;
    }

    public function execute(array $input): array
    {
        $assignment = $this->identity->verifyPair($input);
        $validated = Validator::make($input, [
            'submission_key' => 'required|uuid',
            'topic' => 'required|integer',
            'remark' => 'nullable|string|max:255',
        ], [
            'submission_key.required' => 'A submission key is required. Reload the form before submitting.',
        ])->validate();
        $data = [
            'teacher_name' => $assignment->teacher_name,
            'teacher_code' => $assignment->teacher_code,
            'student_name' => $assignment->student_name,
            'student_code' => $assignment->student_code,
            'topic' => (int) $validated['topic'],
            'remark' => $validated['remark'] ?? null,
        ];
        // Request identity uses the stable topic ID, not a mutable label.
        return $this->writer->submit($validated['submission_key'], 'performance.public.created', $data, function () use ($data) {
            Validator::make($data, ['topic' => 'exists:topics,id'])->validate();
            $data['topic'] = Topic::findOrFail($data['topic'])->name;
            $record = Performance::create($data);

            return [$record, $this->serializer->performance($record)];
        });
    }
}
