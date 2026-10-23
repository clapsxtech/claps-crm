<?php

namespace App\Modules\Integrations\Contracts;

interface SheetsTransport
{
    /** Append exactly once; return the remote updated range when available. */
    public function append(array $payload): ?string;
}
