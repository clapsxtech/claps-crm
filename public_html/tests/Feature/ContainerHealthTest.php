<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ContainerHealthTest extends TestCase
{
    public function test_readiness_does_not_create_database_sessions(): void
    {
        config(['session.driver' => 'database']);
        $queries = [];
        DB::listen(function ($query) use (&$queries) { $queries[] = $query->sql; });

        $response = $this->get('/health');

        $response->assertOk()->assertExactJson(['status' => 'ok']);
        $this->assertSame([], $queries);
        $this->assertFalse($response->headers->has('Set-Cookie'));
    }
    public function test_database_readiness_checks_database_without_sessions(): void
    {
        config(['session.driver' => 'database']);
        $queries = [];
        DB::listen(function ($query) use (&$queries) { $queries[] = $query->sql; });
        $response = $this->get('/ready');
        $response->assertOk()->assertExactJson(['status' => 'ok']);
        $this->assertContains('SELECT 1', $queries);
        $this->assertFalse($response->headers->has('Set-Cookie'));
        $this->assertStringNotContainsString('sessions', implode(' ', $queries));
    }

    public function test_database_readiness_failure_is_sanitized(): void
    {
        $manager = DB::getFacadeRoot();
        try {
            DB::shouldReceive('connection')->once()->andThrow(new \RuntimeException('secret connection details'));
            $this->get('/ready')->assertStatus(503)->assertExactJson(['status' => 'unavailable']);
        } finally { DB::swap($manager); }
    }
}
