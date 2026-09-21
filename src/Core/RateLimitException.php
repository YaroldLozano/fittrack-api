<?php

namespace App\Core;

use RuntimeException;

/** Se superó el límite de mensajes por minuto (mapea a 429). */
class RateLimitException extends RuntimeException
{
}
