<?php
namespace App\Modules\Erp\Services;

/** One stage definition for pipeline cards, counts and filters. Status remains event-derived. */
class SalesPipeline
{
    public const STAGES = ['new'=>'New', 'contacted'=>'Contacted', 'demo_scheduled'=>'Demo scheduled', 'demo_attended'=>'Demo attended', 'joined'=>'Registered', 'lost'=>'Lost'];

    public static function expression(): string
    {
        return "CASE WHEN l.status = 'JOINED' THEN 'joined' WHEN l.status = 'LOST' THEN 'lost'
            WHEN EXISTS (SELECT 1 FROM erp_demos d WHERE d.unit_id = l.unit_id AND d.lead_id = l.id AND d.status IN ('SCHEDULED','RESCHEDULED')) THEN 'demo_scheduled'
            WHEN EXISTS (SELECT 1 FROM erp_demos d WHERE d.unit_id = l.unit_id AND d.lead_id = l.id AND d.status IN ('ATTENDED','JOINED','CONVERTED')) THEN 'demo_attended'
            WHEN EXISTS (SELECT 1 FROM erp_lead_activities a WHERE a.unit_id = l.unit_id AND a.lead_id = l.id AND a.kind = 'contact_attempt') THEN 'contacted'
            ELSE 'new' END";
    }

    public static function contact(object $lead): array
    {
        $digits = preg_replace('/\D/', '', (string) $lead->phone);
        $country = strtoupper(trim((string) ($lead->country ?? '')));
        if (strlen($digits) === 10 && in_array($country, ['IN', 'IND', 'INDIA'], true)) $digits = '91'.$digits;
        // WhatsApp needs an international number. Do not guess a country for old local numbers.
        $international = strlen($digits) >= 11 && strlen($digits) <= 15 && !str_starts_with($digits, '0');
        return ['dial' => $international ? '+'.$digits : preg_replace('/[^0-9+]/', '', (string) $lead->phone), 'whatsapp' => $international ? 'https://wa.me/'.$digits : null];
    }

    /** Student name is optional at enquiry time, so every screen needs the same readable fallback. */
    public static function name(object $lead, string $field = 'name'): string
    {
        $name = trim((string) ($lead->canonical_student_name ?? '')) ?: trim((string) ($lead->$field ?? ''));
        return $name !== '' ? $name : 'Student name pending';
    }

    /** wa.me link with a prefilled message. Without an international number the sender picks the chat (for example a group). */
    public static function whatsappMessage(?string $whatsapp, string $text): string
    {
        return ($whatsapp ?: 'https://wa.me/').'?text='.rawurlencode($text);
    }

    /** One phone per ERP teacher, taken from its live (unmerged) recruitment record. */
    public static function teacherPhones(): \Illuminate\Database\Query\Builder
    {
        return \Illuminate\Support\Facades\DB::table('erp_teacher_candidates')->whereNotNull('teacher_id')->whereNull('merged_into_id')
            ->whereNotNull('phone')->where('phone', '!=', '')->groupBy('teacher_id')->select('teacher_id')->selectRaw('MAX(phone) AS phone');
    }

    /** Teacher numbers come from recruitment records; Indian 10-digit numbers are assumed for teachers. */
    public static function teacherContact(?string $phone): array
    {
        if (trim((string) $phone) === '') return ['dial' => null, 'whatsapp' => null];
        return self::contact((object) ['phone' => $phone, 'country' => 'IN']);
    }

    private static function lines(array $rows): string
    {
        return implode("\n", array_map(fn ($label, $value) => $label.': '.$value, array_keys($rows), $rows));
    }

    private static function demoWhen(object $demo): string
    {
        $date = \Carbon\Carbon::parse($demo->scheduled_on)->format('D, d M Y');
        $time = $demo->scheduled_time ? \Carbon\Carbon::createFromFormat('H:i', substr($demo->scheduled_time, 0, 5))->format('h:i A') : 'To be confirmed';
        return $date.' · '.$time;
    }

    public static function demoParentMessage(object $demo, object $business): string
    {
        $rows = array_filter([
            'Student' => self::name($demo, 'lead_name'),
            'Subject' => $demo->subject_name ?? null,
            'Date & time' => self::demoWhen($demo).' ('.(($demo->timezone ?? '') ?: $business->timezone).')',
            'Teacher' => $demo->teacher_name ?? null,
            'Duration' => !empty($demo->duration_minutes) ? $demo->duration_minutes.' minutes' : null,
            'Meeting link' => $demo->meet_link ?? null,
        ]);
        return "Dear Parent,\nThe demo class is scheduled.\n\n".self::lines($rows)."\n\nPlease join on time. Reply here if you need to change the time.\n— ".$business->name;
    }

    public static function demoTeacherMessage(object $demo, object $business): string
    {
        $rows = array_filter([
            'Student' => self::name($demo, 'lead_name'),
            'Class / syllabus' => trim(($demo->matching_grade ?? $demo->lead_grade ?? '').' '.($demo->matching_syllabus ?? $demo->lead_syllabus ?? '')),
            'Subject' => $demo->subject_name ?? null,
            'Medium' => $demo->matching_medium ?? null,
            'Date & time' => self::demoWhen($demo).' ('.(($demo->timezone ?? '') ?: $business->timezone).')',
            'Duration' => !empty($demo->duration_minutes) ? $demo->duration_minutes.' minutes' : null,
            'Meeting link' => $demo->meet_link ?? null,
        ]);
        return 'Hi '.($demo->teacher_name ?? 'Teacher').",\nA demo class is booked with you.\n\n".self::lines($rows)."\n\nPlease confirm.\n— ".$business->name;
    }

    /** Registration summary that the sales person forwards to the admin group. */
    public static function joinedMessage(object $lead, object $business): string
    {
        $rows = array_filter([
            'Student' => self::name($lead),
            'Student code' => $lead->canonical_student_code ?? $lead->admission_code ?? null,
            'Admission code' => $lead->admission_code ?? null,
            'Joined on' => $lead->joined_on ?? null,
            'Class / grade' => $lead->grade ?? null,
            'Syllabus' => $lead->syllabus ?? null,
            'Subject' => $lead->subject ?? null,
            'Parent phone' => $lead->phone ?? null,
            'Student WhatsApp' => $lead->student_phone ?? null,
            'Source' => $lead->source ?? null,
            'Sales owner' => $lead->assigned_name ?? null,
            'Learning needs' => $lead->learning_requirement ?? null,
            'Parent concerns' => $lead->concern ?? null,
        ]);
        return "NEW ADMISSION — ".$business->name."\n\n".self::lines($rows);
    }

    public static function teacherProfileMessage(object $teacher, object $business): string
    {
        $rows = array_filter([
            'Teacher' => $teacher->name,
            'Code' => $teacher->code,
            'Category' => $teacher->category ?? null,
            'Levels' => $teacher->levels ?? null,
            'Subjects' => $teacher->subjects ?? null,
            'Classes / syllabus' => $teacher->classes ?? null,
            'Teaching medium' => $teacher->mediums ?? null,
            'Qualification' => $teacher->qualification ?? null,
        ]);
        return "Teacher profile — ".$business->name."\n\n".self::lines($rows);
    }
}
