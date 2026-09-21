<?php

namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;

class NotImplementedController
{
    public function stub(Request $request): void
    {
        Response::error('Todavía no implementado (llega en una etapa posterior)', 501);
    }
}
