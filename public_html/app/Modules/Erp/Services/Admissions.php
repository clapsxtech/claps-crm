<?php

namespace App\Modules\Erp\Services;

use App\Models\User;
use App\Modules\IdentityAccess\Services\SiteAccessPolicy;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\Validator;

/**
 * Integrated admissions domain for the former claps-crm workflows.
 *
 * A lead is the sales record. Once it joins, the lead points to the canonical
 * erp_students row and payments are written only to erp_ledger. This keeps
 * sales history without creating a second student or payment database.
 */
class Admissions
{
    private function now(): string
    {
        return now()->format('Y-m-d H:i:s');
    }

    private function fail(string $field, string $message): never
    {
        throw ValidationException::withMessages([$field => $message]);
    }

    private function role(int $unitId, int $actorId): string
    {
        $user = User::find($actorId);
        (new SiteAccessPolicy())->assertSiteMember($user);
        if ((new SiteAccessPolicy())->isAdministrator($user)) {
            return 'superadmin';
        }
        return (string) DB::table('erp_memberships')
            ->where('unit_id', $unitId)
            ->where('user_id', $actorId)
            ->value('role');
    }

    private function canViewAll(int $unitId, int $actorId): bool
    {
        return in_array($this->role($unitId, $actorId), ['superadmin', 'admin', 'staff', 'team_manager', 'read_only'], true);
    }

    private function canManageAll(int $unitId, int $actorId): bool
    {
        return in_array($this->role($unitId, $actorId), ['superadmin', 'admin', 'staff', 'team_manager'], true);
    }

    private function assertLeadAccess(int $unitId, int $actorId, int $leadId, bool $write = true): object
    {
        $lead = DB::table('erp_leads')->where('unit_id', $unitId)->where('id', $leadId)->lockForUpdate()->first();
        abort_unless($lead, 404, 'Lead not found in this business unit.');
        if (!$this->canViewAll($unitId, $actorId)) {
            $owned = (int) $lead->assigned_to === $actorId || ($lead->assigned_to === null && (int) $lead->created_by === $actorId);
            abort_unless($owned, 403, 'This lead is assigned to another counselor.');
        }
        if ($write && $this->role($unitId, $actorId) === 'read_only') {
            abort(403, 'Read-only team members cannot change admissions records.');
        }
        return $lead;
    }

    private function assertUnitUser(int $unitId, int $userId, string $field = 'assigned_to'): void
    {
        $user = User::where('id', $userId)->where('business_id', config('erp.business_id'))->where('status', 1)->whereNull('deleted_at')->first();
        $isAdministrator = $user && (new SiteAccessPolicy())->isAdministrator($user);
        if (!$user || (!$isAdministrator && !DB::table('erp_memberships')->where('unit_id', $unitId)->where('user_id', $userId)->exists())) {
            $this->fail($field, 'Choose an active member of this business unit.');
        }
    }

    private function normalizePhone(?string $value): string
    {
        $value = trim((string) $value);
        $value = preg_replace('/\D+/', '', $value) ?? '';
        if ($value === '' || strlen($value) < 7 || strlen($value) > 40) {
            $this->fail('phone', 'Enter a valid phone number.');
        }
        return $value;
    }

    private function dateValue(?string $value, string $field, string $fallback = ''): string
    {
        $value = trim((string) ($value ?: $fallback));
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            $this->fail($field, 'Use a valid date in YYYY-MM-DD format.');
        }
        try {
            $date = CarbonImmutable::createFromFormat('!Y-m-d', $value);
        } catch (\Throwable) {
            $date = false;
        }
        if (!$date || $date->format('Y-m-d') !== $value) {
            $this->fail($field, 'Use a valid date in YYYY-MM-DD format.');
        }
        return $value;
    }

    private function activity(int $unitId, int $leadId, ?int $actorId, string $kind, string $summary, array $extra = []): int
    {
        $now = $this->now();
        return (int) DB::table('erp_lead_activities')->insertGetId([
            'unit_id' => $unitId,
            'lead_id' => $leadId,
            'actor_id' => $actorId,
            'kind' => $kind,
            'channel' => $extra['channel'] ?? null,
            'outcome' => $extra['outcome'] ?? null,
            'summary' => $summary,
            'metadata' => isset($extra['metadata']) ? json_encode($extra['metadata'], JSON_THROW_ON_ERROR) : null,
            'occurred_at' => $extra['occurred_at'] ?? $now,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    private function leadRow(int $unitId, int $leadId): object
    {
        return DB::table('erp_leads')->where('unit_id', $unitId)->where('id', $leadId)->first();
    }

    private function validation(array $data, array $rules): array
    {
        return Validator::make($data, $rules)->validate();
    }

    public function createLead(int $unitId, int $actorId, array $payload): object
    {
        $this->assertUnitUser($unitId, $actorId, 'created_by');
        $v = $this->validation($payload, [
            'name' => 'required|string|max:255',
            'phone' => 'required|string|max:40',
            'email' => 'nullable|email|max:255',
            'enquiry_date' => 'nullable|date_format:Y-m-d',
            'grade' => 'nullable|string|max:100',
            'subject' => 'nullable|string|max:100',
            'syllabus' => 'nullable|string|max:100',
            'source' => 'nullable|string|max:100',
            'country' => 'nullable|string|max:8',
            'notes' => 'nullable|string|max:4000',
            'student_phone' => 'nullable|string|max:40',
            'assigned_to' => 'nullable|integer|min:1',
            'external_id' => 'nullable|string|max:160',
            'is_hot' => 'nullable|boolean',
            'existing_lead_id' => 'nullable|integer|min:1',
            'create_separate' => 'nullable|boolean',
        ]);
        $phone = $this->normalizePhone($v['phone']);
        $role = $this->role($unitId, $actorId);
        $assignedTo = (int) ($v['assigned_to'] ?? $actorId);
        if (!in_array($role, ['superadmin', 'admin', 'staff', 'team_manager'], true)) {
            $assignedTo = $actorId;
        } else {
            $this->assertUnitUser($unitId, $assignedTo);
        }
        $unit = DB::table('erp_units')->where('id', $unitId)->first();
        abort_unless($unit, 404, 'Business unit not found.');
        $enquiryDate = $this->dateValue($v['enquiry_date'] ?? null, 'enquiry_date', now($unit->timezone)->toDateString());
        $externalId = trim((string) ($v['external_id'] ?? ''));
        $now = $this->now();

        return DB::transaction(function () use ($unitId, $actorId, $v, $phone, $externalId, $assignedTo, $enquiryDate, $now) {
            if (!empty($v['existing_lead_id']) && (!empty($v['create_separate']) || $externalId !== '')) {
                $this->fail('existing_lead_id', 'Choose one identity: existing enquiry, separate student, or external ID.');
            }
            $existing = $externalId !== ''
                ? DB::table('erp_leads')->where('unit_id', $unitId)->where('external_id', $externalId)->lockForUpdate()->first()
                : null;
            if (!empty($v['existing_lead_id'])) {
                $existing = $this->assertLeadAccess($unitId, $actorId, (int) $v['existing_lead_id']);
            }
            if (!$existing && $externalId === '' && empty($v['create_separate'])) {
                $tail = strlen($phone) >= 10 ? substr($phone, -10) : $phone;
                $matches = $this->visibleLeads($unitId, $actorId)->where(function ($q) use ($phone, $tail) {
                    $q->where('l.phone', $phone)->orWhere('l.phone', 'like', '%'.$tail);
                })->limit(5)->get();
                if ($matches->isNotEmpty()) {
                    $choices = $matches->map(fn ($lead) => '#'.$lead->id.' '.$lead->name)->implode('; ');
                    $this->fail('existing_lead_id', 'Possible existing enquiries: '.$choices.'. Enter an existing enquiry ID, or select Separate student to keep a sibling distinct.');
                }
            }
            if ($existing) {
                $this->assertLeadAccess($unitId, $actorId, (int) $existing->id);
                if ($existing->status !== 'LEAD') {
                    $this->activity($unitId, $existing->id, $actorId, 're_enquiry', 'Contact re-enquired; existing lead retained.', ['metadata' => ['external_id' => $externalId, 'status' => $existing->status, 'enquiry_date' => $enquiryDate, 'source' => $v['source'] ?? null]]);
                    return $existing;
                }
                DB::table('erp_leads')->where('id', $existing->id)->update([
                    'updated_at' => $now,
                ]);
                $this->activity($unitId, $existing->id, $actorId, 're_enquiry', 'Contact re-enquired; existing lead retained.', ['metadata' => ['enquiry_date' => $enquiryDate, 'source' => $v['source'] ?? null]]);
                Audit::record($unitId, $actorId, 'lead.re_enquired', 'lead', $existing->id, ['phone' => $phone, 'deduplicated' => true]);
                return $this->leadRow($unitId, $existing->id);
            }

            $id = DB::table('erp_leads')->insertGetId([
                'unit_id' => $unitId,
                'external_id' => $externalId !== '' ? $externalId : null,
                'name' => trim($v['name']),
                'phone' => $phone,
                'email' => isset($v['email']) ? trim($v['email']) : null,
                'enquiry_date' => $enquiryDate,
                'status' => 'LEAD',
                'grade' => isset($v['grade']) ? trim($v['grade']) : null,
                'subject' => isset($v['subject']) ? trim($v['subject']) : null,
                'syllabus' => isset($v['syllabus']) ? trim($v['syllabus']) : null,
                'source' => isset($v['source']) ? trim($v['source']) : null,
                'country' => isset($v['country']) ? strtoupper(trim($v['country'])) : null,
                'notes' => isset($v['notes']) ? trim($v['notes']) : null,
                'student_phone' => !empty($v['student_phone']) ? $this->normalizePhone($v['student_phone']) : null,
                'assigned_to' => $assignedTo,
                'created_by' => $actorId,
                'is_hot' => !empty($v['is_hot']),
                'created_at' => $now,
                'updated_at' => $now,
            ]);
            $this->activity($unitId, $id, $actorId, 'created', 'Lead created.');
            Audit::record($unitId, $actorId, 'lead.created', 'lead', $id, ['assigned_to' => $assignedTo, 'source' => $v['source'] ?? null]);
            return $this->leadRow($unitId, $id);
        });
    }

    public function updateLead(int $unitId, int $actorId, array $payload): object
    {
        $v = $this->validation($payload, [
            'lead_id' => 'required|integer|min:1',
            'name' => 'sometimes|required|string|max:255',
            'phone' => 'sometimes|required|string|max:40',
            'email' => 'sometimes|nullable|email|max:255',
            'grade' => 'sometimes|nullable|string|max:100',
            'subject' => 'sometimes|nullable|string|max:100',
            'syllabus' => 'sometimes|nullable|string|max:100',
            'source' => 'sometimes|nullable|string|max:100',
            'country' => 'sometimes|nullable|string|max:8',
            'notes' => 'sometimes|nullable|string|max:4000',
            'student_phone' => 'sometimes|nullable|string|max:40',
            'assigned_to' => 'sometimes|nullable|integer|min:1',
            'status' => 'sometimes|required|in:LEAD,LOST',
            'lost_reason' => 'sometimes|nullable|string|max:2000',
            'is_hot' => 'sometimes|boolean',
            'interest_status' => 'sometimes|nullable|string|max:30',
            'learning_requirement' => 'sometimes|nullable|string|max:4000',
            'concern' => 'sometimes|nullable|string|max:4000',
            'about_student' => 'sometimes|nullable|string|max:4000',
            'about_parent' => 'sometimes|nullable|string|max:4000',
        ]);
        return DB::transaction(function () use ($unitId, $actorId, $v) {
            $lead = $this->assertLeadAccess($unitId, $actorId, (int) $v['lead_id']);
            if ($lead->status === 'JOINED') {
                $this->fail('lead_id', 'Joined leads are converted records. Update the linked student profile instead.');
            }
            $values = $v;
            unset($values['lead_id']);
            if (array_key_exists('phone', $values)) $values['phone'] = $this->normalizePhone($values['phone']);
            if (array_key_exists('student_phone', $values) && $values['student_phone']) $values['student_phone'] = $this->normalizePhone($values['student_phone']);
            if (array_key_exists('assigned_to', $values)) {
                if (!$this->canManageAll($unitId, $actorId)) $this->fail('assigned_to', 'Only a team manager can reassign a lead.');
                if ($values['assigned_to']) $this->assertUnitUser($unitId, (int) $values['assigned_to']);
            }
            if (($values['status'] ?? null) === 'LOST' && trim((string) ($values['lost_reason'] ?? $lead->lost_reason)) === '') {
                $this->fail('lost_reason', 'Give a reason when marking a lead lost.');
            }
            if (($values['status'] ?? null) === 'LEAD') $values['lost_reason'] = null;
            if (!$values) $this->fail('lead_id', 'Provide at least one lead field to update.');
            $changes = [];
            foreach ($values as $key => $value) if ((string) ($lead->$key ?? '') !== (string) ($value ?? '')) $changes[$key] = ['from' => $lead->$key ?? null, 'to' => $value];
            if ($changes) {
                DB::table('erp_leads')->where('id', $lead->id)->update($values + ['updated_at' => $this->now()]);
                $kind = isset($changes['status']) ? 'status_changed' : 'edited';
                $summary = isset($changes['status']) ? 'Lead status changed to '.$values['status'].'.' : 'Lead details updated.';
                $this->activity($unitId, $lead->id, $actorId, $kind, $summary, ['metadata' => ['changes' => $changes]]);
                Audit::record($unitId, $actorId, 'lead.updated', 'lead', $lead->id, ['changes' => $changes]);
            }
            return $this->leadRow($unitId, $lead->id);
        });
    }

    public function scheduleDemo(int $unitId, int $actorId, array $payload, bool $matched = false): object
    {
        $v = $this->validation($payload, [
            'lead_id' => 'required|integer|min:1',
            'scheduled_on' => 'required|date_format:Y-m-d',
            'scheduled_time' => ['required', 'regex:/^([01]\d|2[0-3]):[0-5]\d$/'],
            'teacher_id' => 'nullable|integer|min:1',
            'subject_id' => 'nullable|integer|min:1',
            'meet_link' => 'nullable|url|max:500',
            'scheduling_notes' => 'nullable|string|max:2000',
        ]);
        return DB::transaction(function () use ($unitId, $actorId, $v, $matched) {
            $lead = $this->assertLeadAccess($unitId, $actorId, (int) $v['lead_id']);
            if(!empty($v['teacher_id']) && !$matched)$this->fail('teacher_id','Use Teacher matching to confirm eligibility, duration, availability and conflicts before assigning a demo teacher.');
            if (($v['teacher_id'] ?? null) && !DB::table('erp_teacher_units')->where('unit_id', $unitId)->where('teacher_id', $v['teacher_id'])->exists()) $this->fail('teacher_id', 'Teacher does not belong to this business unit.');
            if (($v['subject_id'] ?? null) && !DB::table('erp_subjects')->where('id', $v['subject_id'])->exists()) $this->fail('subject_id', 'Choose a valid subject.');
            $now = $this->now();
            $id = DB::table('erp_demos')->insertGetId([
                'unit_id' => $unitId,
                'lead_id' => $lead->id,
                'student_id' => $lead->student_id,
                'teacher_id' => $v['teacher_id'] ?? null,
                'subject_id' => $v['subject_id'] ?? null,
                'scheduled_on' => $v['scheduled_on'],
                'scheduled_time' => $v['scheduled_time'],
                'status' => 'SCHEDULED',
                'meet_link' => $v['meet_link'] ?? null,
                'created_by' => $actorId,
                'scheduling_notes' => isset($v['scheduling_notes']) ? trim($v['scheduling_notes']) : null,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
            DB::table('erp_leads')->where('id', $lead->id)->update(['is_post_demo' => true, 'updated_at' => $now]);
            $this->activity($unitId, $lead->id, $actorId, 'demo_scheduled', 'Demo scheduled for '.$v['scheduled_on'].' at '.$v['scheduled_time'].'.');
            Audit::record($unitId, $actorId, 'demo.scheduled', 'demo', $id, ['lead_id' => $lead->id]);
            return DB::table('erp_demos')->where('id', $id)->first();
        });
    }

    public function updateDemo(int $unitId, int $actorId, array $payload): object
    {
        $v = $this->validation($payload, [
            'demo_id' => 'required|integer|min:1',
            'status' => 'sometimes|required|in:SCHEDULED,RESCHEDULED,ATTENDED,CONVERTED,JOINED,CANCELLED,NO_SHOW',
            'scheduled_on' => 'sometimes|required|date_format:Y-m-d',
            'scheduled_time' => ['sometimes', 'required', 'regex:/^([01]\d|2[0-3]):[0-5]\d$/'],
            'meet_link' => 'sometimes|nullable|url|max:500',
            'scheduling_notes' => 'sometimes|nullable|string|max:2000',
        ]);
        return DB::transaction(function () use ($unitId, $actorId, $v) {
            $demo = DB::table('erp_demos')->where('unit_id', $unitId)->where('id', $v['demo_id'])->lockForUpdate()->first();
            abort_unless($demo, 404, 'Demo not found in this business unit.');
            $this->assertLeadAccess($unitId, $actorId, (int) $demo->lead_id);
            if($demo->starts_at){
                if($demo->enrollment_id)$this->fail('demo_id','This demo already has a regular allocation. Manage the class through its timetable.');
                if(isset($v['scheduled_on']) || isset($v['scheduled_time']) || in_array($v['status']??'', ['RESCHEDULED','SCHEDULED','CONVERTED','JOINED'],true))$this->fail('demo_id','Cancel and rebook a matched demo through Teacher matching. Conversion uses its successful feedback handoff.');
                if(in_array($v['status']??'', ['ATTENDED','NO_SHOW'],true) && $demo->ends_at>gmdate('Y-m-d H:i:s'))$this->fail('status','Wait until the demo has ended before recording its outcome.');
            }
            $values = $v; unset($values['demo_id']);
            if (($values['status'] ?? null) === 'ATTENDED' || ($values['status'] ?? null) === 'CONVERTED' || ($values['status'] ?? null) === 'JOINED') $values['completed_at'] = $demo->completed_at ?: $this->now();
            $values['updated_at'] = $this->now();
            DB::table('erp_demos')->where('id', $demo->id)->update($values);
            if (isset($v['status'])) $this->activity($unitId, $demo->lead_id, $actorId, 'demo_status_changed', 'Demo status changed to '.$v['status'].'.');
            Audit::record($unitId, $actorId, 'demo.updated', 'demo', $demo->id, ['changes' => $values]);
            return DB::table('erp_demos')->where('id', $demo->id)->first();
        });
    }

    public function recordFollowup(int $unitId, int $actorId, array $payload): object
    {
        $v = $this->validation($payload, [
            'lead_id' => 'required|integer|min:1',
            'channel' => 'required|in:whatsapp,call,email,other',
            'note' => 'required|string|max:4000',
            'outcome' => 'required|string|max:1000',
            'next_due_on' => 'nullable|date_format:Y-m-d',
            'interest_status' => 'nullable|string|max:30',
        ]);
        return DB::transaction(function () use ($unitId, $actorId, $v) {
            $lead = $this->assertLeadAccess($unitId, $actorId, (int) $v['lead_id']);
            $now = $this->now();
            $activityId = $this->activity($unitId, $lead->id, $actorId, 'contact_attempt', trim($v['note']), ['channel' => $v['channel'], 'outcome' => trim($v['outcome']), 'metadata' => ['next_due_on' => $v['next_due_on'] ?? null]]);
            DB::table('erp_leads')->where('id', $lead->id)->update([
                'next_follow_up_on' => $v['next_due_on'] ?? null,
                'interest_status' => $v['interest_status'] ?? $lead->interest_status,
                'updated_at' => $now,
            ]);
            Audit::record($unitId, $actorId, 'lead.followup_recorded', 'lead_activity', $activityId, ['lead_id' => $lead->id, 'channel' => $v['channel'], 'outbound_sent' => false]);
            return DB::table('erp_lead_activities')->where('id', $activityId)->first();
        });
    }

    public function createReminder(int $unitId, int $actorId, array $payload): object
    {
        $v = $this->validation($payload, [
            'lead_id' => 'required|integer|min:1',
            'due_on' => 'required|date_format:Y-m-d',
            'due_time' => ['nullable', 'regex:/^([01]\d|2[0-3]):[0-5]\d$/'],
            'text' => 'required|string|max:500',
        ]);
        return DB::transaction(function () use ($unitId, $actorId, $v) {
            $lead = $this->assertLeadAccess($unitId, $actorId, (int) $v['lead_id']);
            $now = $this->now();
            $id = DB::table('erp_lead_reminders')->insertGetId([
                'unit_id' => $unitId, 'lead_id' => $lead->id, 'due_on' => $v['due_on'], 'due_time' => $v['due_time'] ?? null,
                'text' => trim($v['text']), 'created_by' => $actorId, 'created_at' => $now, 'updated_at' => $now,
            ]);
            Audit::record($unitId, $actorId, 'lead.reminder_created', 'lead_reminder', $id, ['lead_id' => $lead->id]);
            return DB::table('erp_lead_reminders')->where('id', $id)->first();
        });
    }

    public function completeReminder(int $unitId, int $actorId, array $payload): object
    {
        $v = $this->validation($payload, ['reminder_id' => 'required|integer|min:1']);
        return DB::transaction(function () use ($unitId, $actorId, $v) {
            $reminder = DB::table('erp_lead_reminders')->where('unit_id', $unitId)->where('id', $v['reminder_id'])->lockForUpdate()->first();
            abort_unless($reminder, 404, 'Reminder not found in this business unit.');
            $this->assertLeadAccess($unitId, $actorId, (int) $reminder->lead_id);
            if (!$reminder->is_completed) DB::table('erp_lead_reminders')->where('id', $reminder->id)->update(['is_completed' => true, 'completed_at' => $this->now(), 'completed_by' => $actorId, 'updated_at' => $this->now()]);
            Audit::record($unitId, $actorId, 'lead.reminder_completed', 'lead_reminder', $reminder->id, ['lead_id' => $reminder->lead_id]);
            return DB::table('erp_lead_reminders')->where('id', $reminder->id)->first();
        });
    }

    public function requestTransfer(int $unitId, int $actorId, array $payload): object
    {
        $v = $this->validation($payload, ['lead_id' => 'required|integer|min:1', 'to_user_id' => 'required|integer|min:1']);
        return DB::transaction(function () use ($unitId, $actorId, $v) {
            $lead = $this->assertLeadAccess($unitId, $actorId, (int) $v['lead_id']);
            $to = (int) $v['to_user_id'];
            if ($to === $actorId) $this->fail('to_user_id', 'Choose another team member.');
            $this->assertUnitUser($unitId, $to, 'to_user_id');
            $from = (int) ($lead->assigned_to ?: $lead->created_by);
            if ($from !== $actorId && !$this->canManageAll($unitId, $actorId)) abort(403, 'Only the current lead owner can request a transfer.');
            $pending = DB::table('erp_lead_transfers')->where('unit_id', $unitId)->where('lead_id', $lead->id)->where('status', 'pending')->lockForUpdate()->first();
            if ($pending) return $pending;
            $id = DB::table('erp_lead_transfers')->insertGetId(['unit_id' => $unitId, 'lead_id' => $lead->id, 'from_user_id' => $from, 'to_user_id' => $to, 'status' => 'pending', 'created_at' => $this->now(), 'updated_at' => $this->now()]);
            $this->activity($unitId, $lead->id, $actorId, 'transfer_requested', 'Lead transfer requested.');
            Audit::record($unitId, $actorId, 'lead.transfer_requested', 'lead_transfer', $id, ['lead_id' => $lead->id, 'from_user_id' => $from, 'to_user_id' => $to]);
            return DB::table('erp_lead_transfers')->where('id', $id)->first();
        });
    }

    public function resolveTransfer(int $unitId, int $actorId, array $payload): object
    {
        $v = $this->validation($payload, ['transfer_id' => 'required|integer|min:1', 'status' => 'required|in:accepted,rejected,cancelled']);
        return DB::transaction(function () use ($unitId, $actorId, $v) {
            $transfer = DB::table('erp_lead_transfers')->where('unit_id', $unitId)->where('id', $v['transfer_id'])->lockForUpdate()->first();
            abort_unless($transfer, 404, 'Transfer request not found in this business unit.');
            if ($transfer->status !== 'pending') return $transfer;
            $allowed = $v['status'] === 'cancelled' ? (int) $transfer->from_user_id === $actorId : (int) $transfer->to_user_id === $actorId;
            if (!$allowed && !$this->canManageAll($unitId, $actorId)) abort(403, 'You cannot resolve this transfer.');
            DB::table('erp_lead_transfers')->where('id', $transfer->id)->update(['status' => $v['status'], 'resolved_at' => $this->now(), 'updated_at' => $this->now()]);
            if ($v['status'] === 'accepted') DB::table('erp_leads')->where('unit_id', $unitId)->where('id', $transfer->lead_id)->update(['assigned_to' => $transfer->to_user_id, 'updated_at' => $this->now()]);
            $this->activity($unitId, $transfer->lead_id, $actorId, 'transfer_'.$v['status'], 'Lead transfer '.$v['status'].'.');
            Audit::record($unitId, $actorId, 'lead.transfer_'.$v['status'], 'lead_transfer', $transfer->id, ['lead_id' => $transfer->lead_id]);
            return DB::table('erp_lead_transfers')->where('id', $transfer->id)->first();
        });
    }

    public function convertLead(int $unitId, int $actorId, array $payload): object
    {
        $v = $this->validation($payload, [
            'lead_id' => 'required|integer|min:1',
            'joined_on' => 'nullable|date_format:Y-m-d',
            'student_code' => 'nullable|string|max:100',
            'student_phone' => 'nullable|string|max:40',
            'guardian_contact' => 'nullable|string|max:255',
            'monthly_fee_paise' => 'nullable|integer|min:0|max:1000000000',
            'fee_per_hour_paise' => 'nullable|integer|min:0|max:1000000000',
            'payment_amount_paise' => 'nullable|integer|min:0|max:100000000000',
            'payment_reference' => 'nullable|string|max:120',
            'learning_requirement' => 'nullable|string|max:4000',
            'concern' => 'nullable|string|max:4000',
            'about_student' => 'nullable|string|max:4000',
            'about_parent' => 'nullable|string|max:4000',
            'teacher_code' => 'nullable|string|max:100',
            'teacher_section' => 'nullable|string|max:100',
        ]);
        return DB::transaction(function () use ($unitId, $actorId, $v) {
            $lead = $this->assertLeadAccess($unitId, $actorId, (int) $v['lead_id']);
            if ($lead->status === 'JOINED' && $lead->student_id) return DB::table('erp_students')->where('unit_id', $unitId)->where('id', $lead->student_id)->first();
            $unit = DB::table('erp_units')->where('id', $unitId)->lockForUpdate()->first();
            abort_unless($unit, 404, 'Business unit not found.');
            $joinedOn = $this->dateValue($v['joined_on'] ?? null, 'joined_on', now($unit->timezone)->toDateString());
            $studentPhone = !empty($v['student_phone']) ? $this->normalizePhone($v['student_phone']) : ($lead->student_phone ?: $lead->phone);
            $admissionCode = $lead->admission_code ?: $this->newAdmissionCode($unitId);
            $studentCode = trim((string) ($v['student_code'] ?? '')) ?: $admissionCode;
            if (DB::table('erp_students')->where('code', $studentCode)->exists()) $this->fail('student_code', 'That student code is already in use.');
            $now = $this->now();
            $studentId = DB::table('erp_students')->insertGetId([
                'unit_id' => $unitId,
                'code' => $studentCode,
                'name' => $lead->name,
                'registered_on' => $joinedOn,
                'timezone' => $unit->timezone,
                'status' => 'active',
                'grade' => $lead->grade ?: 'Not specified',
                'syllabus' => $lead->syllabus ?: 'Not specified',
                'guardian_contact' => $v['guardian_contact'] ?? $lead->phone,
                'meeting_url' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
            $leadValues = [
                'status' => 'JOINED', 'student_id' => $studentId, 'joined_on' => $joinedOn,
                'admission_code' => $admissionCode, 'student_phone' => $studentPhone,
                'monthly_fee_paise' => $v['monthly_fee_paise'] ?? $lead->monthly_fee_paise,
                'fee_per_hour_paise' => $v['fee_per_hour_paise'] ?? $lead->fee_per_hour_paise,
                'teacher_code' => $v['teacher_code'] ?? $lead->teacher_code,
                'teacher_section' => $v['teacher_section'] ?? $lead->teacher_section,
                'learning_requirement' => $v['learning_requirement'] ?? $lead->learning_requirement,
                'concern' => $v['concern'] ?? $lead->concern,
                'about_student' => $v['about_student'] ?? $lead->about_student,
                'about_parent' => $v['about_parent'] ?? $lead->about_parent,
                'updated_at' => $now,
            ];
            DB::table('erp_leads')->where('id', $lead->id)->update($leadValues);

            $amount = (int) ($v['payment_amount_paise'] ?? 0);
            if ($amount > 0) {
                $reference = trim((string) ($v['payment_reference'] ?? ''));
                if ($reference === '') $this->fail('payment_reference', 'A receipt reference is required for the initial payment.');
                (new Finance())->payment($unitId, $actorId, ['student_id' => $studentId, 'amount_paise' => $amount, 'reference' => $reference, 'currency' => 'INR']);
            }
            $this->activity($unitId, $lead->id, $actorId, 'joined', 'Lead converted to joined student.', ['metadata' => ['student_id' => $studentId, 'admission_code' => $admissionCode]]);
            Audit::record($unitId, $actorId, 'lead.converted', 'lead', $lead->id, ['student_id' => $studentId, 'admission_code' => $admissionCode, 'payment_amount_paise' => $amount]);
            return DB::table('erp_students')->where('id', $studentId)->first();
        });
    }

    private function newAdmissionCode(int $unitId): string
    {
        do {
            $code = 'CE'.now()->format('ymdHis').str_pad((string) random_int(0, 99), 2, '0', STR_PAD_LEFT);
        } while (DB::table('erp_leads')->where('unit_id', $unitId)->where('admission_code', $code)->exists() || DB::table('erp_students')->where('code', $code)->exists());
        return $code;
    }

    public function visibleLeads(int $unitId, int $actorId, ?string $search = null, ?string $status = null)
    {
        $query = DB::table('erp_leads as l')
            ->leftJoin('erp_students as s', function ($join) {
                $join->on('s.id', '=', 'l.student_id')->on('s.unit_id', '=', 'l.unit_id');
            })
            ->leftJoin('users as owner', 'owner.id', '=', 'l.assigned_to')
            ->where('l.unit_id', $unitId)
            ->select('l.*', 's.name as canonical_student_name', 's.code as canonical_student_code', 'owner.name as assigned_name');
        if (!$this->canViewAll($unitId, $actorId)) $query->where(function ($q) use ($actorId) { $q->where('l.assigned_to', $actorId)->orWhere(function ($nested) use ($actorId) { $nested->whereNull('l.assigned_to')->where('l.created_by', $actorId); }); });
        if ($search !== null && trim($search) !== '') {
            $needle = '%'.trim($search).'%';
            $query->where(function ($q) use ($needle) { $q->where('l.name', 'like', $needle)->orWhere('l.phone', 'like', $needle)->orWhere('l.email', 'like', $needle)->orWhere('l.admission_code', 'like', $needle)->orWhere('s.name', 'like', $needle); });
        }
        if ($status && in_array($status, ['LEAD', 'JOINED', 'LOST'], true)) $query->where('l.status', $status);
        return $query->orderByDesc('l.enquiry_date')->orderByDesc('l.id');
    }

    public function canViewAllForUnit(int $unitId, int $actorId): bool
    {
        return $this->canViewAll($unitId, $actorId);
    }
}
