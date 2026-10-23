<?php

namespace App\Modules\Academics\Actions;

use App\Models\Marklist;
use App\Modules\Academics\Services\ResultInput;
use App\Modules\Academics\Services\ResultSheetSerializer;
use App\Modules\Integrations\Services\SubmissionWriter;
use App\Modules\People\Services\TeacherIdentityVerifier;

class SubmitMarklist
{
    private $input;
    private $writer;
    private $serializer;
    private $identity;

    public function __construct(ResultInput $input, SubmissionWriter $writer, ResultSheetSerializer $serializer, TeacherIdentityVerifier $identity)
    {
        $this->input = $input;
        $this->writer = $writer;
        $this->serializer = $serializer;
        $this->identity = $identity;
    }

    public function publicSubmission(array $input): array
    {
        // Authenticate the entire teacher/student assignment even on retries.
        $this->identity->verifyPair($input);
        return $this->submit($input, false);
    }

    /** The controller/route must require an authenticated administrator. */
    public function adminSubmission(array $input): array
    {
        return $this->submit($input, true);
    }

    private function submit(array $input, bool $admin): array
    {
        // Hash stable request fields first. Replays must not depend on a topic
        // rename or a snapshot import reassigning legacy admin select IDs.
        [$requestData] = $this->input->marklist($input, $admin, true, false);
        $eventType = $admin ? 'marklist.admin.created' : 'marklist.public.created';

        return $this->writer->submit($input['submission_key'], $eventType, $requestData, function () use ($input, $admin) {
            [$data, $names] = $this->input->marklist($input, $admin);
            $record = Marklist::create($data);

            return [$record, $this->serializer->marklist($record, $names)];
        });
    }
}
