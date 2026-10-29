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
}
