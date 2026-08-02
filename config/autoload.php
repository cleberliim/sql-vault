<?php

/**
 * Autoloader simples (PSR-4-like) para o namespace App\.
 * Mapeia App\Models\QueryModel -> app/Models/QueryModel.php
 */
spl_autoload_register(function (string $class): void {
    $prefix = 'App\\';

    if (strncmp($prefix, $class, strlen($prefix)) !== 0) {
        return;
    }

    $relative = substr($class, strlen($prefix));
    $path = __DIR__ . '/../app/' . str_replace('\\', '/', $relative) . '.php';

    if (file_exists($path)) {
        require $path;
    }
});
