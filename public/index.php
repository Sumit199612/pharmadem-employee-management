<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/src/autoload.php';

use App\Core\Auth;
use App\Core\Env;
use App\Core\Session;

Env::load(dirname(__DIR__) . '/.env');
Session::start();

if (Auth::check()) {
    header('Location: ' . Auth::dashboardUrl());
    exit;
}

header('Location: /login.php');
exit;
