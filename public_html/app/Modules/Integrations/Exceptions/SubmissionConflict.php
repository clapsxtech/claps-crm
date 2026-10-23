<?php

namespace App\Modules\Integrations\Exceptions;

use RuntimeException;

class SubmissionConflict extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('This submission key was already used for different data. Reload the form to start a new submission.');
    }
}
