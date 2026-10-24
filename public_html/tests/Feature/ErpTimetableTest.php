<?php
namespace Tests\Feature;

use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/** Real GET routes and Blade rendering with synthetic, in-memory records only. */
class ErpTimetableTest extends TestCase
{
    private $unit;
    private $other;
    private $admin;
    private $student;
    private $teacher;
    private $subject;
    private $enrollment;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = $this->createAdmin();
        $this->unit = DB::table('erp_units')->insertGetId(['code' => 'WEEK-AA', 'name' => 'Synthetic Weekly Unit', 'timezone' => 'UTC']);
        $this->other = DB::table('erp_units')->insertGetId(['code' => 'WEEK-BB', 'name' => 'Private Other Unit', 'timezone' => 'UTC']);
        DB::table('erp_memberships')->insert(['unit_id' => $this->unit, 'user_id' => $this->admin->id, 'role' => 'admin']);
        $this->student = $this->student('WEEK-STUDENT', 'Synthetic Weekly Student');
        $this->teacher = $this->teacher('WEEK-TEACHER', 'Synthetic Weekly Teacher');
        $this->subject = DB::table('erp_subjects')->insertGetId(['name' => 'Synthetic Weekly Mathematics']);
        $this->enrollment = DB::table('erp_enrollments')->insertGetId([
            'unit_id' => $this->unit, 'student_id' => $this->student, 'teacher_id' => $this->teacher,
            'subject_id' => $this->subject, 'starts_on' => '2026-01-01', 'status' => 'active',
            'student_rate_paise' => 12000, 'teacher_rate_paise' => 6000, 'currency' => 'INR',
        ]);
        $this->actingAs($this->admin);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        CarbonImmutable::setTestNow();
        parent::tearDown();
    }

    private function student(string $code, string $name, ?int $unit = null): int
    {
        return DB::table('erp_students')->insertGetId([
            'unit_id' => $unit ?? $this->unit, 'code' => $code, 'name' => $name,
            'registered_on' => '2026-01-01', 'timezone' => 'Pacific/Honolulu',
            'status' => 'active', 'grade' => '8', 'syllabus' => 'Synthetic',
        ]);
    }

    private function teacher(string $code, string $name, ?int $unit = null): int
    {
        $id = DB::table('erp_teachers')->insertGetId([
            'code' => $code, 'name' => $name, 'timezone' => 'Europe/London', 'status' => 'active',
        ]);
        DB::table('erp_teacher_units')->insert(['teacher_id' => $id, 'unit_id' => $unit ?? $this->unit]);
        return $id;
    }

    private function lesson(string $starts, string $ends, array $attributes = []): int
    {
        return DB::table('erp_lessons')->insertGetId(array_merge([
            'unit_id' => $this->unit, 'enrollment_id' => $this->enrollment,
            'student_id' => $this->student, 'teacher_id' => $this->teacher, 'subject_id' => $this->subject,
            'starts_at' => $starts, 'ends_at' => $ends, 'status' => 'scheduled', 'kind' => 'regular',
            'student_rate_paise' => 12000, 'teacher_rate_paise' => 6000, 'currency' => 'INR',
        ], $attributes));
    }

    private function url(array $query = [], ?int $unit = null): string
    {
        return route('erp.business', [$unit ?? $this->unit, 'timetable'])
            .'?'.http_build_query(array_merge(['date' => '2026-10-21'], $query));
    }

    private function ids(array $timetable, ?string $date = null): array
    {
        $lessons = $date !== null ? $timetable['days'][$date]['lessons']
            : collect($timetable['days'])->flatMap(function ($day) { return $day['lessons']; });
        return $lessons->pluck('id')->map(function ($id) { return (int) $id; })->unique()->values()->all();
    }

    public function test_week_is_monday_to_monday_and_uses_half_open_overlap_boundaries(): void
    {
        $this->lesson('2026-10-18 22:00:00', '2026-10-19 00:00:00'); // Ends at the excluded boundary.
        $carryIn = $this->lesson('2026-10-18 23:30:00', '2026-10-19 00:30:00');
        $monday = $this->lesson('2026-10-19 00:00:00', '2026-10-19 01:00:00');
        $sunday = $this->lesson('2026-10-25 23:00:00', '2026-10-26 00:00:00');
        $carryOut = $this->lesson('2026-10-25 23:30:00', '2026-10-26 00:30:00');
        $this->lesson('2026-10-26 00:00:00', '2026-10-26 01:00:00');

        $response = $this->get($this->url())->assertOk();
        $week = $response->viewData('timetable');
        $this->assertInstanceOf(CarbonImmutable::class, $week['start']);
        $this->assertSame('2026-10-19 00:00:00 +00:00', $week['start']->format('Y-m-d H:i:s P'));
        $this->assertSame('2026-10-26 00:00:00 +00:00', $week['end']->format('Y-m-d H:i:s P'));
        $this->assertSame(['2026-10-19', '2026-10-20', '2026-10-21', '2026-10-22', '2026-10-23', '2026-10-24', '2026-10-25'], array_keys($week['days']));
        $this->assertSame([$carryIn, $monday, $sunday, $carryOut], $this->ids($week));
        $this->assertSame([$carryIn, $monday], $this->ids($week, '2026-10-19'));
        $this->assertSame([$sunday, $carryOut], $this->ids($week, '2026-10-25'));
        $this->assertSame(4, $week['count']);
        $this->assertFalse($week['truncated']);
        $response->assertSee('Synthetic Weekly Student')->assertSee('WEEK-STUDENT')
            ->assertSee('Synthetic Weekly Teacher')->assertSee('WEEK-TEACHER')
            ->assertSee('Synthetic Weekly Mathematics');
        $this->assertArrayNotHasKey('lessons', $response->viewData('data'));
        $this->assertArrayHasKey('agreements', $response->viewData('data'));
        $this->assertArrayHasKey('exceptions', $response->viewData('data'));
    }

    public function test_cross_midnight_lessons_appear_on_each_overlapping_local_day_but_not_their_exclusive_end_day(): void
    {
        DB::table('erp_units')->where('id', $this->unit)->update(['timezone' => 'Asia/Kolkata']);
        $monday = $this->lesson('2026-10-18 19:00:00', '2026-10-18 20:00:00');
        $crossMidnight = $this->lesson('2026-10-19 18:00:00', '2026-10-19 19:00:00');
        $endsAtMidnight = $this->lesson('2026-10-20 17:30:00', '2026-10-20 18:30:00');
        $this->lesson('2026-10-25 18:30:00', '2026-10-25 19:30:00');

        $response = $this->get($this->url())->assertOk()->assertSee('Asia/Kolkata')->assertSee('+05:30');
        $week = $response->viewData('timetable');
        $this->assertSame('Asia/Kolkata', $week['start']->timezoneName);
        $this->assertSame('2026-10-18 18:30:00', $week['start']->utc()->format('Y-m-d H:i:s'));
        $this->assertSame([$monday, $crossMidnight], $this->ids($week, '2026-10-19'));
        $this->assertSame([$crossMidnight, $endsAtMidnight], $this->ids($week, '2026-10-20'));
        $this->assertSame([], $this->ids($week, '2026-10-21'));
        $this->assertSame(3, $week['count']); // Unique lessons, not calendar-card count.
    }

    public function test_default_week_uses_the_business_timezone_not_the_server_or_person_timezone(): void
    {
        DB::table('erp_units')->where('id', $this->unit)->update(['timezone' => 'Asia/Kolkata']);
        Carbon::setTestNow(CarbonImmutable::parse('2026-10-18 20:00:00', 'UTC'));
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-10-18 20:00:00', 'UTC'));
        $week = $this->get(route('erp.business', [$this->unit, 'timetable']))->assertOk()->viewData('timetable');
        $this->assertSame('2026-10-19', $week['start']->toDateString());
        $this->assertSame('Asia/Kolkata', $week['start']->timezoneName);
        $this->assertSame(0, $week['count']);
        $this->assertCount(7, $week['days']);
    }

    public function test_spring_dst_week_has_local_midnight_boundaries_and_displays_both_offsets(): void
    {
        DB::table('erp_units')->where('id', $this->unit)->update(['timezone' => 'America/New_York']);
        $spring = $this->lesson('2026-03-08 06:30:00', '2026-03-08 07:30:00');
        $last = $this->lesson('2026-03-09 03:30:00', '2026-03-09 04:00:00');
        $this->lesson('2026-03-09 04:00:00', '2026-03-09 05:00:00');
        $response = $this->get($this->url(['date' => '2026-03-08']))->assertOk()
            ->assertSee('America/New_York')->assertSee('-05:00')->assertSee('-04:00');
        $week = $response->viewData('timetable');
        $this->assertSame('2026-03-02 05:00:00', $week['start']->utc()->format('Y-m-d H:i:s'));
        $this->assertSame('2026-03-09 04:00:00', $week['end']->utc()->format('Y-m-d H:i:s'));
        $this->assertSame(167 * 3600, $week['end']->timestamp - $week['start']->timestamp);
        $this->assertSame([$spring, $last], $this->ids($week, '2026-03-08'));
    }

    public function test_fall_dst_keeps_repeated_local_hours_distinct_and_in_chronological_order(): void
    {
        DB::table('erp_units')->where('id', $this->unit)->update(['timezone' => 'America/New_York']);
        $second = $this->lesson('2026-11-01 06:30:00', '2026-11-01 06:50:00');
        $first = $this->lesson('2026-11-01 05:30:00', '2026-11-01 05:50:00');
        $response = $this->get($this->url(['date' => '2026-11-01']))->assertOk()->assertSee('-04:00')->assertSee('-05:00');
        $week = $response->viewData('timetable');
        $this->assertSame('2026-10-26 04:00:00', $week['start']->utc()->format('Y-m-d H:i:s'));
        $this->assertSame('2026-11-02 05:00:00', $week['end']->utc()->format('Y-m-d H:i:s'));
        $this->assertSame(169 * 3600, $week['end']->timestamp - $week['start']->timestamp);
        $this->assertSame([$first, $second], $this->ids($week, '2026-11-01'));
        $this->assertSame(2, $week['count']);
    }

    public function test_student_and_teacher_name_or_code_filters_combine_with_status_and_week(): void
    {
        $student = $this->student('FILTER-OTHER-STUDENT', 'Other Pupil');
        $teacher = $this->teacher('FILTER-OTHER-TEACHER', 'Other Tutor');
        $match = $this->lesson('2026-10-20 09:00:00', '2026-10-20 10:00:00', ['status' => 'completed']);
        $scheduled = $this->lesson('2026-10-20 11:00:00', '2026-10-20 12:00:00');
        $cancelled = $this->lesson('2026-10-20 12:00:00', '2026-10-20 13:00:00', ['status' => 'cancelled']);
        $otherStudent = $this->lesson('2026-10-20 13:00:00', '2026-10-20 14:00:00', ['student_id' => $student, 'status' => 'completed']);
        $otherTeacher = $this->lesson('2026-10-20 14:00:00', '2026-10-20 15:00:00', ['teacher_id' => $teacher, 'status' => 'completed']);
        $this->lesson('2026-10-27 09:00:00', '2026-10-27 10:00:00', ['status' => 'completed']);

        foreach ([['student' => 'Weekly Student', 'teacher' => 'WEEK-TEACHER'], ['student' => 'WEEK-STUDENT', 'teacher' => 'Weekly Teacher']] as $filters) {
            $response = $this->get($this->url($filters + ['status' => 'completed']))->assertOk();
            $this->assertSame([$match], $this->ids($response->viewData('timetable')));
            $response->assertDontSee('Other Pupil')->assertDontSee('Other Tutor');
        }
        $cases = [
            ['status' => 'scheduled', 'ids' => [$scheduled]],
            ['status' => 'cancelled', 'ids' => [$cancelled]],
            ['status' => '', 'ids' => [$match, $scheduled, $cancelled, $otherStudent, $otherTeacher]],
        ];
        foreach ($cases as $case) {
            $week = $this->get($this->url(['status' => $case['status']]))->assertOk()->viewData('timetable');
            $this->assertSame($case['ids'], $this->ids($week));
        }
        $week = $this->get($this->url(['student' => 'not-present']))->assertOk()->viewData('timetable');
        $this->assertSame(0, $week['count']);
        $this->assertFalse($week['truncated']);
    }

    public function test_names_codes_subjects_and_reflected_filter_values_are_html_escaped(): void
    {
        $studentName = '<script>alert("student")</script>';
        $teacherName = '<img src=x onerror=alert("teacher")>';
        $subjectName = '<svg onload=alert("subject")>';
        DB::table('erp_students')->where('id', $this->student)->update(['name' => $studentName, 'code' => 'S<code>']);
        DB::table('erp_teachers')->where('id', $this->teacher)->update(['name' => $teacherName, 'code' => 'T<code>']);
        DB::table('erp_subjects')->where('id', $this->subject)->update(['name' => $subjectName]);
        $id = $this->lesson('2026-10-20 10:00:00', '2026-10-20 11:00:00');
        $response = $this->get($this->url(['student' => $studentName, 'teacher' => $teacherName]))->assertOk();
        $this->assertSame([$id], $this->ids($response->viewData('timetable')));
        foreach ([$studentName, $teacherName, $subjectName, 'S<code>', 'T<code>'] as $unsafe) {
            $response->assertSee(e($unsafe), false)->assertDontSee($unsafe, false);
        }
        $this->assertSame($studentName, $response->viewData('timetable')['filters']['student']);
        $attack = "' OR 1=1 --";
        $this->assertSame([], $this->ids($this->get($this->url(['student' => $attack]))->assertOk()->viewData('timetable')));
    }

    public function test_rescheduled_and_no_show_history_remain_filterable_without_live_scheduling_actions(): void
    {
        $rescheduled = $this->lesson('2026-10-20 10:00:00', '2026-10-20 11:00:00', ['status' => 'rescheduled']);
        $replacement = $this->lesson('2026-10-21 10:00:00', '2026-10-21 11:00:00', ['replaces_lesson_id' => $rescheduled]);
        $noShow = $this->lesson('2026-10-22 10:00:00', '2026-10-22 11:00:00', ['status' => 'no_show']);
        $response = $this->get($this->url(['status' => 'rescheduled']))->assertOk()
            ->assertSee('Original time retained for history')->assertDontSee('Reschedule with agreement')
            ->assertDontSee('Cancel without charge')->assertDontSee('Record attended minutes');
        $this->assertSame([$rescheduled], $this->ids($response->viewData('timetable')));
        $response = $this->get($this->url(['status' => 'no_show']))->assertOk()
            ->assertSee('No show')->assertSee('No attended minutes recorded')
            ->assertDontSee('Reschedule with agreement')->assertDontSee('Cancel without charge');
        $this->assertSame([$noShow], $this->ids($response->viewData('timetable')));
        $response = $this->get($this->url(['status' => 'scheduled']))->assertOk()
            ->assertSee('Reschedule with agreement')->assertSee('Cancel without charge');
        $this->assertSame([$replacement], $this->ids($response->viewData('timetable')));
        $this->assertSame([$rescheduled, $replacement, $noShow], $this->ids($this->get($this->url())->assertOk()->viewData('timetable')));
    }

    public function test_week_navigation_preserves_filters_and_does_not_link_beyond_supported_date_bounds(): void
    {
        $filters = ['student' => 'WEEK-STUDENT', 'teacher' => 'WEEK-TEACHER', 'status' => 'scheduled'];
        $response = $this->get($this->url($filters))->assertOk();
        foreach (['2026-10-12', '2026-10-26'] as $date) {
            $expected = route('erp.business', array_merge([$this->unit, 'timetable'], ['date' => $date], $filters));
            $response->assertSee('href="'.e($expected).'"', false);
            $this->get($expected)->assertOk();
        }
        $this->get($this->url(['date' => '2000-01-03']))->assertOk()->assertDontSee('rel="prev"', false);
        $this->get($this->url(['date' => '2099-12-27']))->assertOk()->assertDontSee('rel="next"', false);
    }

    public function test_foreign_lessons_and_inconsistent_student_or_teacher_joins_never_leak_into_the_calendar(): void
    {
        $foreignStudent = $this->student('WEEK-SECRET-STUDENT', 'Private Foreign Student', $this->other);
        $foreignTeacher = $this->teacher('WEEK-SECRET-TEACHER', 'Private Foreign Teacher', $this->other);
        $own = $this->lesson('2026-10-20 10:00:00', '2026-10-20 11:00:00');
        $this->lesson('2026-10-20 10:00:00', '2026-10-20 11:00:00', ['unit_id' => $this->other, 'student_id' => $foreignStudent, 'teacher_id' => $foreignTeacher]);
        $this->lesson('2026-10-20 11:00:00', '2026-10-20 12:00:00', ['student_id' => $foreignStudent]);
        $this->lesson('2026-10-20 12:00:00', '2026-10-20 13:00:00', ['teacher_id' => $foreignTeacher]);
        DB::table('erp_students')->where('id', $this->student)->update(['guardian_contact' => 'PRIVATE-GUARDIAN-CONTACT', 'meeting_url' => 'https://example.invalid/private-meeting']);
        $response = $this->get($this->url())->assertOk()->assertDontSee('Private Foreign')
            ->assertDontSee('WEEK-SECRET')->assertDontSee('PRIVATE-GUARDIAN-CONTACT')
            ->assertDontSee('https://example.invalid/private-meeting');
        $this->assertSame([$own], $this->ids($response->viewData('timetable')));
        $this->get($this->url([], $this->other))->assertForbidden();

        DB::table('erp_memberships')->insert(['unit_id' => $this->other, 'user_id' => $this->admin->id, 'role' => 'admin']);
        $response = $this->get($this->url(['student' => 'WEEK-SECRET']))->assertOk()->assertDontSee('Private Foreign');
        $this->assertSame([], $this->ids($response->viewData('timetable')));
    }

    public function test_timetable_requires_explicit_active_site_and_admin_unit_membership(): void
    {
        foreach (['teacher', 'sales'] as $role) {
            $member = $this->createMember();
            DB::table('erp_memberships')->insert(['unit_id' => $this->unit, 'user_id' => $member->id, 'role' => $role]);
            $this->actingAs($member)->get($this->url())->assertForbidden();
        }
        $this->actingAs($this->createMember())->get($this->url())->assertForbidden();
        foreach ([['business_id' => 999], ['status' => 0]] as $attributes) {
            $member = $this->createMember($attributes);
            DB::table('erp_memberships')->insert(['unit_id' => $this->unit, 'user_id' => $member->id, 'role' => 'admin']);
            $this->actingAs($member)->get($this->url())->assertForbidden();
        }
        $this->app['auth']->guard()->logout();
        $this->get($this->url())->assertRedirect(route('login'));
    }

    /** @dataProvider invalidFilters */
    public function test_malformed_and_unbounded_filters_are_validation_errors(array $query, string $field): void
    {
        $this->getJson($this->url($query))->assertUnprocessable()->assertJsonValidationErrors($field);
    }

    public static function invalidFilters(): array
    {
        return [
            'invalid calendar day' => [['date' => '2026-02-30'], 'date'],
            'non ISO date' => [['date' => '10/21/2026'], 'date'],
            'timestamp instead of date' => [['date' => '2026-10-21T00:00:00Z'], 'date'],
            'date array' => [['date' => ['2026-10-21']], 'date'],
            'before lower bound' => [['date' => '2000-01-02'], 'date'],
            'after upper bound' => [['date' => '2099-12-28'], 'date'],
            'unknown status' => [['status' => 'approved'], 'status'],
            'status array' => [['status' => ['scheduled']], 'status'],
            'student array' => [['student' => ['WEEK-STUDENT']], 'student'],
            'teacher array' => [['teacher' => ['WEEK-TEACHER']], 'teacher'],
            'student length' => [['student' => str_repeat('s', 101)], 'student'],
            'teacher length' => [['teacher' => str_repeat('t', 101)], 'teacher'],
        ];
    }

    public function test_supported_date_bounds_and_empty_filters_render_without_errors(): void
    {
        foreach (['2000-01-03', '2099-12-27', ''] as $date) {
            $response = $this->get($this->url(['date' => $date, 'student' => '', 'teacher' => '', 'status' => '']))->assertOk();
            $this->assertCount(7, $response->viewData('timetable')['days']);
        }
        $this->get($this->url(['student' => str_repeat('s', 100), 'teacher' => str_repeat('t', 100)]))->assertOk();
    }

    public function test_calendar_caps_unique_lessons_at_250_in_stable_order_and_warns_only_when_truncated(): void
    {
        $ids = [];
        for ($i = 0; $i < 250; $i++) {
            $ids[] = $this->lesson('2026-10-20 10:00:00', '2026-10-20 11:00:00');
        }
        $week = $this->get($this->url())->assertOk()->assertDontSee('Showing only the first 250')->viewData('timetable');
        $this->assertSame(250, $week['count']);
        $this->assertFalse($week['truncated']);
        $this->assertSame($ids, $this->ids($week));
        $this->lesson('2026-10-20 10:00:00', '2026-10-20 11:00:00');
        $response = $this->get($this->url())->assertOk()->assertSee('Showing only the first 250 matching lessons')
            ->assertSee('This week is incomplete')->assertSee('Empty days below may have omitted lessons');
        $week = $response->viewData('timetable');
        $this->assertSame(250, $week['count']);
        $this->assertTrue($week['truncated']);
        $this->assertSame($ids, $this->ids($week));
    }

    public function test_calendar_reads_preserve_all_erp_records_finance_audit_and_outbox_state(): void
    {
        $lesson = $this->lesson('2026-10-20 10:00:00', '2026-10-20 11:00:00', ['status' => 'completed']);
        DB::table('erp_attendance')->insert(['unit_id' => $this->unit, 'lesson_id' => $lesson, 'actual_minutes' => 60, 'status' => 'submitted', 'submitted_by' => $this->admin->id]);
        DB::table('erp_ledger')->insert(['unit_id' => $this->unit, 'student_id' => $this->student, 'kind' => 'payment', 'amount_paise' => 12000, 'currency' => 'INR', 'reference' => 'WEEK-RECORDED-PAYMENT', 'event_key' => 'week-payment', 'occurred_at' => '2026-10-19 08:00:00', 'created_by' => $this->admin->id]);
        $tables = array_values(array_filter(DB::connection()->getDoctrineSchemaManager()->listTableNames(), function ($table) {
            return strpos($table, 'erp_') === 0 || $table === 'integration_outbox';
        }));
        $snapshot = function () use ($tables) {
            $state = [];
            foreach ($tables as $table) $state[$table] = DB::table($table)->orderBy('id')->get()->toJson();
            return $state;
        };
        $before = $snapshot();
        foreach ([[], ['status' => 'completed'], ['student' => 'WEEK-STUDENT'], ['date' => '2026-10-28']] as $query) {
            $this->get($this->url($query))->assertOk();
        }
        $this->getJson($this->url(['status' => 'invalid']))->assertUnprocessable();
        $this->assertSame($before, $snapshot());
    }
}
