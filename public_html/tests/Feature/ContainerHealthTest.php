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
}
