<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/src/autoload.php';

use App\Core\Auth;
use App\Core\Session;

Session::start();
Auth::logout();

Session::start();
Session::setFlash('success', 'You have been safely logged out.');
header('Location: /login.php');
exit;
