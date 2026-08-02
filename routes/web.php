<?php

use App\Router;
use App\Controllers\QueryController;

$router = new Router();

// View principal (SPA)
$router->get('/', function () {
    require __DIR__ . '/../app/Views/index.php';
});

// API REST de queries
$router->get('/api/queries', [QueryController::class, 'index']);
$router->get('/api/queries/{id}', [QueryController::class, 'show']);
$router->post('/api/queries', [QueryController::class, 'store']);
$router->put('/api/queries/{id}', [QueryController::class, 'update']);
$router->delete('/api/queries/{id}', [QueryController::class, 'destroy']);
$router->post('/api/queries/{id}/favorite', [QueryController::class, 'favorite']);
$router->post('/api/queries/{id}/duplicate', [QueryController::class, 'duplicate']);

return $router;
