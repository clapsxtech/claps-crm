<?php

namespace App\Modules\Integrations\Exceptions;

use RuntimeException;

/** Only use when it is certain that no append request was dispatched. */
class DeliveryNotDispatched extends RuntimeException
{
}
