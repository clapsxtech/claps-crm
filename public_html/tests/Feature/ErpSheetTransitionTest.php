<?php

namespace Tests\Feature;

use App\Modules\Erp\Services\{SheetTransition,TransitionSheetMapper,TransitionSheetReader};
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Tests\TestCase;

class ErpSheetTransitionTest extends TestCase
{
    private int $unit;
    private int $other;
    private array $rows;
    private $readHook = null;

    protected function setUp(): void
    {
        parent::setUp();
        $this->unit = DB::table('erp_units')->insertGetId(['code'=>'AA','name'=>'AA','timezone'=>'Asia/Kolkata']);
        $this->other = DB::table('erp_units')->insertGetId(['code'=>'BB','name'=>'BB','timezone'=>'Asia/Kolkata']);
        config(['sheet_transition.enabled'=>true,'sheet_transition.authority'=>'application','sheet_transition.band_units'=>['AA'=>$this->unit,'BB'=>$this->other]]);
        $this->rows = [2=>$this->student()];
        $reader = $this->createMock(TransitionSheetReader::class);
        $reader->method('read')->willReturnCallback(function ($source,$heartbeat) {
            if ($this->readHook) ($this->readHook)($heartbeat);
            $heartbeat();
            return ['version'=>'123','rows'=>$this->rows];
        });
        $this->app->instance(TransitionSheetReader::class,$reader);
    }

    private function student(string $code='CCA100', string $name='Example Student', string $band='AA'): array
    {
        $row = array_fill(0,29,'');
        $row[0]=$band; $row[1]=$name; $row[2]='Ongoing'; $row[3]=$code; $row[27]='8'; $row[28]='CBSE';
        // Fields outside the mapping must not be retained.
        $row[17]='PRIVATE DOB'; $row[20]='PRIVATE ADDRESS';
        return $row;
    }

    private function refresh(string $source='students', bool $publish=true, ?string $approve=null): array
    {
        return $this->app->make(SheetTransition::class)->refresh($source,$publish,$approve);
    }

    public function test_preview_never_writes_and_publish_never_touches_canonical_tables(): void
    {
        $report=$this->refresh('students',false);
        $this->assertSame('ready',$report['status']);
        $this->assertDatabaseCount('erp_sheet_sources',0);
        $this->assertDatabaseCount('erp_sheet_records',0);
        $report=$this->refresh();
        $this->assertSame('ready',$report['status']);
        $this->assertDatabaseCount('erp_sheet_records',1);
        $this->assertDatabaseCount('erp_students',0);
        $this->assertDatabaseCount('erp_attendance',0);
        $payload=DB::table('erp_sheet_records')->value('payload');
        $this->assertStringNotContainsString('PRIVATE',$payload);
        $health = (new \App\Modules\Erp\Services\OperationalHealth())->inspect()['checks']['sheet_transition'];
        $this->assertSame('warning',$health['status']);
        $this->assertSame(4,$health['stale_sources']);
    }

    public function test_replay_reuses_snapshot_and_updates_freshness_without_duplicate_records(): void
    {
        $first=$this->refresh();
        $this->travel(70)->minutes();
        $second=$this->refresh();
        $this->assertSame($first['digest'],$second['digest']);
        $this->assertSame($first['run_id'],DB::table('erp_sheet_sources')->value('active_run_id'));
        $this->assertDatabaseCount('erp_sheet_records',1);
        $this->assertSame('unchanged',DB::table('erp_sheet_runs')->where('id',$second['run_id'])->value('status'));
        $this->actingAs($this->createAdmin())->get(route('erp.sheets'))->assertOk()->assertDontSee('older than the refresh target');
    }

    public function test_duplicate_keys_quarantine_both_rows_and_keep_previous_snapshot(): void
    {
        $first=$this->refresh();
        $this->rows[3]=$this->student('CCA100','Other Person');
        $report=$this->refresh();
        $this->assertSame('review',$report['status']); $this->assertSame(2,$report['issues']);
        $this->assertSame($first['run_id'],DB::table('erp_sheet_sources')->value('active_run_id'));
        $this->assertSame(2,DB::table('erp_sheet_records')->where('run_id',$report['run_id'])->where('issue','duplicate_source_identity')->count());
    }

    public function test_empty_source_does_not_wipe_data_even_with_removal_approval(): void
    {
        $first=$this->refresh(); $this->rows=[];
        $preview=$this->refresh('students',false);
        $report=$this->refresh('students',true,$preview['digest']);
        $this->assertSame('empty_source',$report['reason']);
        $this->assertSame($first['run_id'],DB::table('erp_sheet_sources')->value('active_run_id'));
    }

    public function test_removals_require_exact_digest_and_do_not_delete_previous_rows(): void
    {
        $this->rows[3]=$this->student('CCA101','Second Student');
        $first=$this->refresh(); unset($this->rows[3]);
        $review=$this->refresh();
        $this->assertSame('source_identities_removed',$review['reason']);
        $this->assertSame('review',$this->refresh('students',true,str_repeat('0',64))['status']);
        $this->assertSame('ready',$this->refresh('students',true,$review['digest'])['status']);
        $this->assertSame(2,DB::table('erp_sheet_records')->where('run_id',$first['run_id'])->count());
    }

    public function test_canonical_identity_and_team_conflicts_are_not_overwritten(): void
    {
        DB::table('erp_students')->insert(['unit_id'=>$this->other,'code'=>'CCA100','name'=>'Example Student','registered_on'=>'2026-01-01','timezone'=>'UTC','grade'=>'8','syllabus'=>'CBSE']);
        $report=$this->refresh();
        $this->assertSame('review',$report['status']);
        $this->assertSame('identity_team_conflict',DB::table('erp_sheet_records')->value('issue'));
        $this->assertNull(DB::table('erp_sheet_sources')->value('active_run_id'));
        $this->assertSame($this->other,(int)DB::table('erp_students')->value('unit_id'));
    }

    public function test_unknown_bands_and_formula_errors_require_review(): void
    {
        $this->rows=[2=>$this->student('CCA100','Example','UNKNOWN'),3=>$this->student('CCA101','#REF!')];
        $report=$this->refresh(); $this->assertSame(2,$report['issues']);
        $this->assertEqualsCanonicalizing(['unmapped_or_archived_team','formula_error'],DB::table('erp_sheet_records')->pluck('issue')->all());
    }

    public function test_failed_read_preserves_good_copy_and_sanitizes_error(): void
    {
        $first=$this->refresh();
        $this->readHook=fn()=>throw new RuntimeException('secret private upstream body');
        try { $this->refresh(); $this->fail('Expected failure'); }
        catch(RuntimeException $e) { $this->assertSame('refresh_failed',$e->getMessage()); }
        $this->assertSame($first['run_id'],DB::table('erp_sheet_sources')->value('active_run_id'));
        $this->assertSame('failed',DB::table('erp_sheet_sources')->value('last_status'));
        $this->assertStringNotContainsString('secret',json_encode(DB::table('erp_sheet_runs')->get()));
    }

    public function test_overlapping_refresh_cannot_claim_or_publish(): void
    {
        $this->readHook=function () {
            $this->readHook=null;
            try { $this->refresh(); $this->fail('Expected concurrent claim to fail'); }
            catch(RuntimeException $e) { $this->assertSame('refresh_already_running',$e->getMessage()); }
        };
        $this->assertSame('ready',$this->refresh()['status']);
        $this->assertDatabaseCount('erp_sheet_runs',1);
    }

    public function test_expired_worker_cannot_publish_after_replacement_claim(): void
    {
        $this->readHook=function () {
            $this->readHook=null; $this->travel(6)->minutes();
            $this->refresh();
        };
        try { $this->refresh(); $this->fail('Expected lease loss'); }
        catch(RuntimeException $e) { $this->assertSame('lease_lost',$e->getMessage()); }
        $this->assertSame('ready',DB::table('erp_sheet_sources')->value('last_status'));
        $this->assertDatabaseCount('erp_sheet_records',1);
    }

    public function test_identity_changes_during_read_block_publication(): void
    {
        $this->readHook=function () { DB::table('erp_units')->where('id',$this->unit)->update(['archived_at'=>now()]); };
        try { $this->refresh(); $this->fail('Expected changed dependencies'); }
        catch(RuntimeException $e) { $this->assertSame('identity_sources_changed',$e->getMessage()); }
        $this->assertNull(DB::table('erp_sheet_sources')->value('active_run_id'));
    }

    public function test_attendance_links_by_codes_and_never_creates_approved_attendance(): void
    {
        $this->refresh();
        $this->rows=[3=>['AA','General','Example Teacher','CT100','Available','Part time','Maths']]; $this->refresh('teachers');
        $this->rows=[2=>['123','Example Teacher','CT100','Example Student','CCA100','2026-10-26','1.5','BB']];
        $report=$this->refresh('attendance');
        $this->assertSame('ready',$report['status']);
        $this->assertSame($this->unit,(int)DB::table('erp_sheet_records')->where('run_id',$report['run_id'])->value('unit_id'));
        $this->assertDatabaseCount('erp_attendance',0);
        $this->rows[2][5]='2026-02-30';
        $this->assertSame('review',$this->refresh('attendance')['status']);
    }

    public function test_blank_and_error_balances_are_never_zero_and_negative_balance_is_preserved(): void
    {
        $this->refresh();
        $row=array_fill(0,19,''); $row[0]='Example Student'; $row[1]='CCA100'; $row[10]='AA'; $row[18]='-123.4567';
        $this->rows=[3=>$row]; $report=$this->refresh('balances');
        $this->assertSame('ready',$report['status']);
        $this->assertStringContainsString('-123.4567',DB::table('erp_sheet_records')->where('run_id',$report['run_id'])->value('payload'));
        $this->rows[3][18]=''; $this->assertSame('review',$this->refresh('balances')['status']);
        $this->rows[3][18]='#VALUE!'; $this->assertSame('review',$this->refresh('balances')['status']);
    }

    public function test_timetable_requires_unambiguous_student_code_and_keeps_sheet_labels(): void
    {
        $this->refresh(); $row=array_fill(0,31,''); $row[0]='Example Student CCA100'; $row[2]='8 CBSE'; $row[3]='5 PM'; $row[4]='Maths'; $row[5]='Teacher Label'; $row[6]='1';
        $this->rows=[4=>$row]; $this->assertSame('ready',$this->refresh('timetable')['status']);
        $this->rows[4][0]='CCA100 CCA101'; $this->assertSame('review',$this->refresh('timetable')['status']);
    }

    public function test_workspace_is_team_scoped_and_reviews_are_administrator_only(): void
    {
        $this->rows[3]=$this->student('CCA101','Other Team Student','BB'); $this->refresh();
        $user=$this->createMember();
        DB::table('erp_memberships')->insert(['unit_id'=>$this->unit,'user_id'=>$user->id,'role'=>'academics']);
        $this->actingAs($user)->get(route('erp.sheets',['unit'=>$this->unit]))->assertOk()->assertSee('Example Student')->assertDontSee('Other Team Student')->assertHeader('Cache-Control','no-store, private');
        $this->get(route('erp.sheets',['unit'=>$this->other]))->assertForbidden();
        $this->get(route('erp.sheets',['review'=>1]))->assertForbidden();
        $this->get(route('erp.sheets',['source'=>'balances']))->assertForbidden();
    }

    public function test_sheets_authority_blocks_new_and_legacy_writes_but_keeps_reads_and_accounts(): void
    {
        config(['sheet_transition.authority'=>'sheets']);
        $this->actingAs($this->createAdmin());
        $this->post('/erp/units/'.$this->unit.'/payment',[])->assertStatus(409);
        $this->post('/upload-workhour',[])->assertStatus(409);
        $this->post('/erp/migration-center/execute',[])->assertStatus(409);
        $this->get('/admin/delete/workhour/1')->assertStatus(409);
        $this->get(route('erp.sheets'))->assertOk()->assertSee('temporary source of truth');
        $this->get(route('account.security'))->assertOk();
    }

    public function test_disabled_transition_cannot_read_or_publish(): void
    {
        config(['sheet_transition.enabled'=>false]);
        $this->actingAs($this->createAdmin())->get(route('erp.sheets'))->assertNotFound();
        $this->expectExceptionMessage('transition_disabled'); $this->refresh();
    }

    public function test_sheets_authority_allows_the_unnamed_login_post_route(): void
    {
        config(['sheet_transition.authority'=>'sheets']);
        $admin=$this->createAdmin();
        $this->post('/login',['email'=>$admin->email,'password'=>'Local-Testing-Only-2026','portal'=>'superadmin'])->assertRedirect('/');
        $this->assertAuthenticatedAs($admin);
    }

    public function test_retention_keeps_active_snapshot_and_latest_review(): void
    {
        $first=$this->refresh();
        $this->rows[2][1]='Wrong Name'; $oldReview=$this->refresh();
        $this->travel(8)->days(); $latest=$this->refresh();
        $this->assertSame(0,$this->artisan('erp:sheets-prune'));
        $this->assertDatabaseHas('erp_sheet_runs',['id'=>$first['run_id']]);
        $this->assertDatabaseHas('erp_sheet_runs',['id'=>$latest['run_id']]);
        $this->assertDatabaseMissing('erp_sheet_runs',['id'=>$oldReview['run_id']]);
    }
}
