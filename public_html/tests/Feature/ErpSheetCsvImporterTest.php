<?php

namespace Tests\Feature;

use App\Modules\Erp\Services\SheetCsvImporter;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ErpSheetCsvImporterTest extends TestCase
{
    public function test_snapshot_preview_counts_normalized_records_without_database_writes(): void
    {
        $directory = $this->fixtureDirectory();
        $this->writeFixtures($directory);

        $preview = (new SheetCsvImporter())->preview($directory);

        $this->assertTrue($preview['valid']);
        $this->assertSame(1, $preview['counts']['students']);
        $this->assertSame(1, $preview['counts']['teachers']);
        $this->assertSame(1, $preview['counts']['tasks']);
        $this->assertSame(1, $preview['counts']['task_templates']);
        $this->assertNotEmpty($preview['snapshot_digest']);
        $this->assertSame(0, DB::table('erp_students')->count());
    }

    public function test_execute_enriches_canonical_profiles_and_idempotent_tasks(): void
    {
        $directory = $this->fixtureDirectory();
        $this->writeFixtures($directory);
        $actor = $this->createAdmin();
        $unit = DB::table('erp_units')->insertGetId(['code' => 'SHEET-AA', 'name' => 'Sheets import unit', 'timezone' => 'UTC']);

        $importer = new SheetCsvImporter();
        $result = $importer->execute($directory, $unit, $actor->id);
        $this->assertSame(1, $result['counts']['students']);
        $this->assertSame(1, $result['counts']['student_profiles']);
        $this->assertSame(1, $result['counts']['teachers']);
        $this->assertSame(1, $result['counts']['tasks']);
        $this->assertDatabaseHas('erp_students', ['unit_id' => $unit, 'code' => 'CCA99999', 'name' => 'Imported Student']);
        $this->assertDatabaseHas('erp_student_profiles', ['student_contact' => '9900001111', 'section' => 'UP', 'hour_rate_paise' => 20833]);
        $this->assertDatabaseHas('erp_teachers', ['code' => 'CT9999', 'name' => 'Imported Teacher']);
        $this->assertDatabaseHas('erp_task_items', ['title' => 'Demo scheduled']);

        $again = $importer->execute($directory, $unit, $actor->id);
        $this->assertSame(0, $again['counts']['students']);
        $this->assertSame(0, $again['counts']['tasks']);
        $this->assertSame(1, DB::table('erp_tasks')->where('unit_id', $unit)->count());
    }

    private function fixtureDirectory(): string
    {
        $directory = sys_get_temp_dir().'/claps-sheet-import-'.bin2hex(random_bytes(4));
        mkdir($directory, 0700, true);
        $this->directories[] = $directory;
        return $directory;
    }

    private array $directories = [];

    protected function tearDown(): void
    {
        foreach ($this->directories as $directory) {
            foreach (glob($directory.'/*') ?: [] as $file) @unlink($file);
            @rmdir($directory);
        }
        parent::tearDown();
    }

    private function writeFixtures(string $directory): void
    {
        $student = array_fill(0, 39, '');
        $student[0] = 'AA'; $student[1] = 'Imported Student'; $student[2] = 'OnGoing ✅'; $student[3] = 'CCA99999'; $student[4] = '208.33'; $student[5] = 'UP'; $student[13] = '+91 99000 01111'; $student[23] = '99000 01111'; $student[24] = '99000 01111'; $student[25] = 'student@example.test'; $student[26] = 'Test School'; $student[27] = '8'; $student[28] = 'CBSE'; $student[29] = 'English'; $student[30] = '6 PM'; $student[32] = '15/11/2020 15:38:37';
        $this->writeCsv($directory.'/student-registration.csv', [array_fill(0, 39, 'header'), $student]);

        $teacher = array_fill(0, 23, '');
        $teacher[2] = 'CT9999'; $teacher[3] = 'Imported Teacher'; $teacher[6] = 'Available✅'; $teacher[8] = 'STANDARD'; $teacher[14] = '919900002222';
        $this->writeCsv($directory.'/exam-results.csv', [array_fill(0, 23, 'header'), $teacher]);

        $task = array_fill(0, 28, '');
        $task[0] = '06/03/2026'; $task[1] = 'Imported Student CCA99999'; $task[3] = 'DEMO ARRANGED'; $task[5] = 'SAFA'; $task[6] = 'AA Imported Student CCA99999'; $task[9] = '0.30'; $task[10] = '07/03/2026'; $task[11] = 'Completed✅'; $task[12] = 'Demo scheduled'; $task[13] = 'TRUE';
        $this->writeCsv($directory.'/task-manager.csv', [array_fill(0, 28, 'header'), $task]);
    }

    private function writeCsv(string $path, array $rows): void
    {
        $handle = fopen($path, 'wb');
        foreach ($rows as $row) fputcsv($handle, $row);
        fclose($handle);
    }
}
