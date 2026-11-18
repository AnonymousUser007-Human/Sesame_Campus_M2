<?php
declare(strict_types=1);
header('Content-Type: text/plain; charset=utf-8');
header('Cache-Control: no-store');
try {
    require dirname(__DIR__) . '/app/bootstrap.php';
    db()->query('SELECT 1');
    echo 'ok';
} catch (Throwable $e) {
    http_response_code(503);
    echo 'fail';
}
