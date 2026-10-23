<?php

namespace App\Modules\Integrations\Exceptions;

use RuntimeException;

/** The remote append might have succeeded. Never automatically resend. */
class DeliveryUncertain extends RuntimeException
{
}
