<?php
namespace Tests\Feature;

use App\Http\Controllers\Erp\TeacherController;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Config\Repository;
use Illuminate\Container\Container;
use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Facade;
use Illuminate\Translation\ArrayLoader;
use Illuminate\Translation\Translator;
use Illuminate\Validation\Factory;
use PHPUnit\Framework\TestCase;

class ErpTeacherAccessTest extends TestCase
{
    private $container;
    protected function setUp(): void {
        parent::setUp(); $this->container=new Container(); Container::setInstance($this->container);
        $this->container->instance('config',new Repository(['erp'=>['business_id'=>1,'finance_policy'=>'admin_import_delivered_only']]));
        $capsule=new Capsule($this->container); $capsule->addConnection(['driver'=>'sqlite','database'=>':memory:','prefix'=>'']); $capsule->setAsGlobal();
        $this->container->instance('db',$capsule->getDatabaseManager()); $this->container->instance('db.schema',$capsule->schema());
        $this->container->instance('validator',new Factory(new Translator(new ArrayLoader(),'en'),$this->container));
        Facade::clearResolvedInstances(); Facade::setFacadeApplication($this->container);
        require_once __DIR__.'/../../database/migrations/2026_10_23_130000_create_erp_core_tables.php'; (new \CreateErpCoreTables())->up();
        require_once __DIR__.'/../../database/migrations/2026_10_23_150000_create_erp_business_workflows.php'; (new \CreateErpBusinessWorkflows())->up();
        require_once __DIR__.'/../../database/migrations/2026_10_23_170000_add_intervals_to_erp_exceptions.php'; (new \AddIntervalsToErpExceptions())->up();
        (require __DIR__.'/../../database/migrations/2026_10_25_150000_add_team_lifecycle.php')->up();
        require_once __DIR__.'/../../database/migrations/2026_10_25_180000_add_erp_tuition_workflows.php'; (new \AddErpTuitionWorkflows())->up();
        DB::table('erp_units')->insert([['id'=>1,'code'=>'SYN-AA','name'=>'Synthetic AA'],['id'=>2,'code'=>'SYN-BB','name'=>'Synthetic BB']]);
        DB::table('erp_teachers')->insert([['id'=>1,'user_id'=>11,'code'=>'SYN-T1','name'=>'Synthetic Teacher','timezone'=>'Asia/Kolkata','status'=>'active'],['id'=>2,'user_id'=>22,'code'=>'SYN-T2','name'=>'Other Teacher','timezone'=>'Asia/Kolkata','status'=>'active']]);
        DB::table('erp_teacher_units')->insert([['teacher_id'=>1,'unit_id'=>1],['teacher_id'=>2,'unit_id'=>2]]);
        DB::table('erp_memberships')->insert([['user_id'=>11,'unit_id'=>1,'role'=>'teacher'],['user_id'=>22,'unit_id'=>2,'role'=>'teacher']]);
        DB::table('erp_students')->insert([['id'=>1,'unit_id'=>1,'code'=>'SYN-S1','name'=>'Own Student','registered_on'=>'2026-01-01','timezone'=>'Asia/Kolkata','status'=>'active','grade'=>'8','syllabus'=>'Test'],['id'=>2,'unit_id'=>2,'code'=>'SYN-S2','name'=>'Private Other Student','registered_on'=>'2026-01-01','timezone'=>'Asia/Kolkata','status'=>'active','grade'=>'8','syllabus'=>'Test']]);
        DB::table('erp_subjects')->insert(['id'=>1,'name'=>'Test Subject']);
        foreach ([1,2] as $id) {
            DB::table('erp_enrollments')->insert(['id'=>$id,'unit_id'=>$id,'student_id'=>$id,'teacher_id'=>$id,'subject_id'=>1,'starts_on'=>'2026-01-01','student_rate_paise'=>12000,'teacher_rate_paise'=>6000,'currency'=>'INR','status'=>'active']);
            DB::table('erp_lessons')->insert(['id'=>$id,'unit_id'=>$id,'enrollment_id'=>$id,'student_id'=>$id,'teacher_id'=>$id,'subject_id'=>1,'starts_at'=>'2026-01-05 10:00:00','ends_at'=>'2026-01-05 11:00:00','status'=>'scheduled','kind'=>'regular','student_rate_paise'=>12000,'teacher_rate_paise'=>6000,'currency'=>'INR']);
        }
    }
    protected function tearDown(): void { \Mockery::close(); DB::disconnect(); Facade::clearResolvedInstances(); parent::tearDown(); }
    private function request(array $payload=[],array $attributes=[]): Request {
        $user=new User(); $user->forceFill(array_merge(['id'=>11,'business_id'=>1,'status'=>1,'deleted_at'=>null],$attributes));
        $r=Request::create('/erp/teacher','POST',$payload); $r->setUserResolver(fn()=> $user); return $r;
    }
    private function denied(callable $call): void { try { $call(); $this->fail('Expected teacher authorization denial.'); } catch (AuthorizationException $e) { $this->assertNotEmpty($e->getMessage()); } }
    public function test_unlinked_users_wrong_site_inactive_and_wrong_role_are_denied(): void {
        $c=new TeacherController();
        $guest=Request::create('/erp/teacher'); $guest->setUserResolver(fn()=> null);
        $this->denied(fn()=> $c->index($guest));
        $this->denied(fn()=> $c->index($this->request([],['id'=>99])));
        $this->denied(fn()=> $c->index($this->request([],['business_id'=>2])));
        $this->denied(fn()=> $c->index($this->request([],['status'=>0])));
        DB::table('erp_memberships')->where('user_id',11)->update(['role'=>'admin']);
        $this->denied(fn()=> $c->index($this->request()));
    }
    public function test_listing_contains_only_own_lessons_and_no_financial_or_contact_fields(): void {
        $data=null; $view=\Mockery::mock(\Illuminate\Contracts\View\View::class);
        $factory=\Mockery::mock(\Illuminate\Contracts\View\Factory::class);
        $factory->shouldReceive('make')->once()->withArgs(function($name,$values) use (&$data) { $data=$values; return $name==='erp.teacher'; })->andReturn($view);
        $this->container->instance(\Illuminate\Contracts\View\Factory::class,$factory);
        (new TeacherController())->index($this->request(['from'=>'2026-01-01','through'=>'2026-01-31']));
        $this->assertCount(1,$data['lessons']); $lesson=$data['lessons']->first(); $this->assertSame('Own Student',$lesson->student_name);
        foreach (['student_rate_paise','teacher_rate_paise','guardian_contact','meeting_url'] as $field) $this->assertFalse(property_exists($lesson,$field));
    }
    public function test_other_teacher_or_crossunit_lessons_cannot_be_submitted(): void {
        $c=new TeacherController(); $this->denied(fn()=> $c->submit($this->request(['lesson_id'=>2,'actual_minutes'=>60])));
        DB::table('erp_lessons')->where('id',1)->update(['teacher_id'=>2]);
        $this->denied(fn()=> $c->submit($this->request(['lesson_id'=>1,'actual_minutes'=>60])));
        DB::table('erp_lessons')->where('id',1)->update(['teacher_id'=>1]);
        DB::table('erp_lessons')->where('id',2)->update(['teacher_id'=>1]);
        $this->denied(fn()=> $c->submit($this->request(['lesson_id'=>2,'actual_minutes'=>60])));
        $this->assertSame(0,DB::table('erp_attendance')->count());
        DB::table('erp_memberships')->where('user_id',11)->update(['unit_id'=>2]);
        $this->denied(fn()=> $c->submit($this->request(['lesson_id'=>1,'actual_minutes'=>60])));
    }
    public function test_teacher_can_submit_own_attendance_without_financial_side_effects(): void {
        $redirect=\Mockery::mock(\Illuminate\Routing\Redirector::class); $response=\Mockery::mock(\Illuminate\Http\RedirectResponse::class);
        $redirect->shouldReceive('route')->once()->with('erp.teacher.index')->andReturn($response); $response->shouldReceive('with')->once()->andReturnSelf(); $this->container->instance('redirect',$redirect);
        (new TeacherController())->submit($this->request(['lesson_id'=>1,'actual_minutes'=>45,'unit_id'=>2,'teacher_id'=>2]));
        $row=DB::table('erp_attendance')->first(); $this->assertSame(1,(int)$row->lesson_id); $this->assertSame(1,(int)$row->unit_id); $this->assertSame(11,(int)$row->submitted_by); $this->assertSame(45,(int)$row->actual_minutes); $this->assertSame('submitted',$row->status);
        $this->assertSame(0,DB::table('erp_ledger')->count());
    }
}
