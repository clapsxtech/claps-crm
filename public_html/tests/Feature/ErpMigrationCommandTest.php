<?php

namespace Tests\Feature;

use App\Console\Commands\PreviewErpMigration;
use Illuminate\Container\Container;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Tester\CommandTester;

class ErpMigrationCommandTest extends TestCase
{
    private function execute(string $input, bool $literalPath = false): array
    {
        $file = $literalPath ? $input : tempnam(sys_get_temp_dir(), 'synthetic-erp-preview-');
        if (!$literalPath) file_put_contents($file, $input);
        try {
            $command = new PreviewErpMigration();
            $command->setLaravel(new Container());
            $tester = new CommandTester($command);
            $status = $tester->execute(['file' => $file]);
            return [$status, json_decode($tester->getDisplay(), true), $tester->getDisplay()];
        } finally {
            if (!$literalPath) unlink($file);
        }
    }

    private function envelope(): array
    {
        return ['rows' => [
            ['type' => 'student', 'external_id' => 'SYN-1', 'unit_code' => 'AA', 'name' => 'Synthetic Private Name', 'timezone' => 'Asia/Kolkata', 'registered_on' => '2026-01-01', 'grade' => '8', 'syllabus' => 'Synthetic'],
        ], 'references' => ['unit_codes' => ['AA'], 'subject_codes' => [], 'cutoff_date' => '2026-10-01', 'source_manifest' => ['source_id' => 'SYN-EXPORT', 'exported_at' => '2026-10-01T00:00:00Z']]];
    }

    public function test_valid_synthetic_envelope_prints_only_summary_and_never_imports(): void
    {
        [$status, $result, $output] = $this->execute(json_encode($this->envelope()));
        $this->assertSame(0, $status);
        $this->assertTrue($result['valid']);
        $this->assertTrue($result['dry_run']);
        $this->assertFalse($result['can_import']);
        $this->assertSame(0, $result['writes']);
        $this->assertSame(1, $result['counts']['student']);
        $this->assertStringNotContainsString('Synthetic Private Name', $output);
        $this->assertStringNotContainsString('SYN-1', $output);
    }

    public function test_blocking_issues_return_nonzero_without_personal_rows(): void
    {
        $data = $this->envelope(); unset($data['rows'][0]['external_id']);
        [$status, $result, $output] = $this->execute(json_encode($data));
        $this->assertSame(1, $status);
        $this->assertFalse($result['valid']);
        $this->assertNull($result['totals']);
        $this->assertContains('invalid_stable_id', array_column($result['issues'], 'code'));
        $this->assertStringNotContainsString('Synthetic Private Name', $output);
    }

    public function test_bad_json_envelopes_catalogs_and_network_paths_fail_closed(): void
    {
        foreach (['{bad', 'null', '{}', '{"rows":[],"references":{"unit_codes":"bad"}}'] as $input) {
            [$status, $result] = $this->execute($input);
            $this->assertSame(1, $status);
            $this->assertFalse($result['valid']);
            $this->assertSame(0, $result['writes']);
        }
        foreach (['https://example.invalid/private.json', 'php://stdin', '/nonexistent-synthetic-file.json'] as $path) {
            [$status, $result] = $this->execute($path, true);
            $this->assertSame(1, $status);
            $this->assertSame('unreadable_local_file', $result['issues'][0]['code']);
        }
    }
}
