<?php

declare(strict_types=1);

$configFile = __DIR__ . '/config.php';
if (!is_file($configFile)) {
    $configFile = __DIR__ . '/config.example.php';
}
$config = require $configFile;

require_once __DIR__ . '/src/helpers.php';
require_once __DIR__ . '/src/Database.php';
require_once __DIR__ . '/src/Auth.php';
require_once __DIR__ . '/src/Csrf.php';
require_once __DIR__ . '/src/FormService.php';
require_once __DIR__ . '/src/WebhookService.php';

if (PHP_SAPI !== 'cli') {
    session_name((string)($config['session_name'] ?? 'formrelay_session'));
    session_set_cookie_params([
        'httponly' => true,
        'secure' => (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'),
        'samesite' => 'Lax',
        'path' => '/',
    ]);
    session_start();
}

$db = new Database((string)$config['db_path']);
$db->migrate();
$auth = new Auth($db, $config);
$csrf = new Csrf();
$forms = new FormService($db, $config);
$webhooks = new WebhookService($db);
