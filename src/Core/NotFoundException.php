<?php

namespace App\Core;

use RuntimeException;

/** El recurso no existe o no pertenece al usuario autenticado (mapea a 404). */
class NotFoundException extends RuntimeException
{
}
