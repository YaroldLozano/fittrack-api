<?php

namespace App\Core;

use RuntimeException;

/** El recurso existe pero la acción está bloqueada por una regla de negocio (mapea a 403). */
class ForbiddenException extends RuntimeException
{
}
