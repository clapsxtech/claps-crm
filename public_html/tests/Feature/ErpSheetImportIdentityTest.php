<?php

namespace Tests\Feature;

use App\Modules\Erp\Services\SheetCsvImporter;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Tests\TestCase;

class ErpSheetImportIdentityTest extends TestCase
{
    private ?string $directory = null;

    protected function tearDown(): void
    {
        if ($this->directory) {
            foreach (glob($this->directory.'/*') ?: [] as $file) unlink($file);
            rmdir($this->directory);
        }
        parent::tearDown();
    }

    public function test_cross_team_student_is_rejected_with_and_without_overwrite_before_any_writes(): void
    {
        [$importer, $unit, $actor] = $this->fixture();
        $other = DB::table('erp_units')->insertGetId(['code'=>'OTHER', 'name'=>'Other', 'timezone'=>'UTC']);
        DB::table('erp_students')->insert(['unit_id'=>$other, 'code'=>'CCA99999', 'name'=>'Imported Student', 'registered_on'=>'2026-01-01', 'timezone'=>'UTC', 'grade'=>'8', 'syllabus'=>'CBSE']);
        foreach ([false, true] as $overwrite) {
            $this->assertRejected($importer, $unit, $actor, $overwrite);
            $this->assertSame(0, DB::table('erp_student_profiles')->count());
            $this->assertSame(0, DB::table('erp_teachers')->count());
            $this->assertSame($other, (int) DB::table('erp_students')->value('unit_id'));
        }
    }

    public function test_cross_team_teacher_is_rejected_before_student_chunks_commit(): void
    {
        [$importer, $unit, $actor] = $this->fixture();
        $other = DB::table('erp_units')->insertGetId(['code'=>'OTHER', 'name'=>'Other', 'timezone'=>'UTC']);
        $teacher = DB::table('erp_teachers')->insertGetId(['code'=>'CT9999', 'name'=>'Imported Teacher', 'timezone'=>'UTC']);
        DB::table('erp_teacher_units')->insert(['teacher_id'=>$teacher, 'unit_id'=>$other]);
        foreach ([false, true] as $overwrite) {
            $this->assertRejected($importer, $unit, $actor, $overwrite);
            $this->assertSame(0, DB::table('erp_students')->count());
            $this->assertSame(0, DB::table('erp_teacher_profiles')->count());
            $this->assertSame($other, (int) DB::table('erp_teacher_units')->value('unit_id'));
        }
    }

    public function test_same_team_code_with_different_name_requires_reconciliation(): void
    {
        [$importer, $unit, $actor] = $this->fixture();
        DB::table('erp_students')->insert(['unit_id'=>$unit, 'code'=>'CCA99999', 'name'=>'Different Person', 'registered_on'=>'2026-01-01', 'timezone'=>'UTC', 'grade'=>'8', 'syllabus'=>'CBSE']);
        $this->assertRejected($importer, $unit, $actor, true);
        $this->assertSame('Different Person', DB::table('erp_students')->value('name'));
        $this->assertSame(0, DB::table('erp_student_profiles')->count());
    }

    public function test_same_team_teacher_code_with_different_name_is_not_overwritten(): void
    {
        [$importer, $unit, $actor] = $this->fixture();
        DB::table('erp_teachers')->insert(['code'=>'CT9999', 'name'=>'Different Teacher', 'timezone'=>'UTC']);
        $this->assertRejected($importer, $unit, $actor, true);
        $this->assertSame('Different Teacher', DB::table('erp_teachers')->value('name'));
        $this->assertSame(0, DB::table('erp_students')->count());
    }

    public function test_existing_profile_team_mismatch_is_not_silently_repaired_by_overwrite(): void
    {
        [$importer, $unit, $actor] = $this->fixture();
        $importer->execute($this->directory, $unit, $actor);
        $other = DB::table('erp_units')->insertGetId(['code'=>'OTHER', 'name'=>'Other', 'timezone'=>'UTC']);
        DB::table('erp_student_profiles')->update(['unit_id'=>$other]);
        $this->assertRejected($importer, $unit, $actor, true);
        $this->assertSame($other, (int) DB::table('erp_student_profiles')->value('unit_id'));
    }

    public function test_same_identity_replay_allows_case_and_whitespace_variation(): void
    {
        [$importer, $unit, $actor] = $this->fixture();
        $importer->execute($this->directory, $unit, $actor);
        DB::table('erp_students')->update(['name'=>'  IMPORTED   STUDENT ']);
        DB::table('erp_teachers')->update(['name'=>'  IMPORTED   TEACHER ']);
        $result = $importer->execute($this->directory, $unit, $actor);
        $this->assertSame(0, $result['writes']);
        $this->assertSame(1, DB::table('erp_students')->count());
        $this->assertSame(1, DB::table('erp_teachers')->count());
    }

    private function assertRejected(SheetCsvImporter $importer, int $unit, int $actor, bool $overwrite): void
    {
        try {
            $importer->execute($this->directory, $unit, $actor, $overwrite);
            $this->fail('A conflicting source identity must fail closed.');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString('Reconcile it before import', $e->getMessage());
        }
    }

    private function fixture(): array
    {
        $this->directory = sys_get_temp_dir().'/claps-import-identity-'.bin2hex(random_bytes(8));
        mkdir($this->directory, 0700);
        $student = array_fill(0, 39, '');
        $student[0]='AA'; $student[1]='Imported Student'; $student[2]='OnGoing ✅'; $student[3]='CCA99999'; $student[4]='200'; $student[27]='8'; $student[28]='CBSE'; $student[32]='01/01/2026';
        $teacher = array_fill(0, 23, '');
        $teacher[2]='CT9999'; $teacher[3]='Imported Teacher'; $teacher[6]='Available✅';
        foreach (['student-registration.csv'=>$student, 'exam-results.csv'=>$teacher] as $file=>$row) {
            $handle = fopen($this->directory.'/'.$file, 'wb');
            fputcsv($handle, array_fill(0, count($row), 'header'), ',', '"', '');
            fputcsv($handle, $row, ',', '"', '');
            fclose($handle);
        }
        $actor = $this->createAdmin();
        $unit = DB::table('erp_units')->insertGetId(['code'=>'IMPORT', 'name'=>'Import', 'timezone'=>'UTC']);
        return [new SheetCsvImporter(), $unit, (int) $actor->id];
    }
}
