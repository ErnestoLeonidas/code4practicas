<?php

namespace App\Controllers;

use App\Config;
use App\Http\Response;

final class HealthController
{
    public function index(array $params): void
    {
        Response::json([
            'status'  => 'ok',
            'version' => Config::get('app_version', '0.0.1'),
        ]);
    }
}
