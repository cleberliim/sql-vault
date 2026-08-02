<?php

/**
 * SQL Vault - Front Controller
 *
 * Este arquivo é usado como "router script" do servidor embutido do PHP:
 *   php -S 127.0.0.1:8756 index.php
 *
 * Requisições para arquivos reais existentes (assets/css, assets/js,
 * assets/icons, favicon, etc.) são servidas diretamente pelo servidor
 * embutido (retornamos false). Todo o restante passa pelo roteador da
 * aplicação (rotas dinâmicas + API JSON).
 */

$uri = urldecode((string) parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH));
$filePath = __DIR__ . $uri;

if ($uri !== '/' && file_exists($filePath) && is_file($filePath)) {
    return false;
}

require __DIR__ . '/config/autoload.php';
require __DIR__ . '/app/Helpers.php';

$router = require __DIR__ . '/routes/web.php';
$router->dispatch($_SERVER['REQUEST_METHOD'], $_SERVER['REQUEST_URI']);
