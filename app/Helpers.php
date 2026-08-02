<?php

/**
 * Envia uma resposta JSON e encerra a execução.
 */
function json_response(mixed $data, int $status = 200): void
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

/**
 * Lê e decodifica o corpo JSON de uma requisição (POST/PUT).
 */
function json_body(): array
{
    $raw = file_get_contents('php://input');

    if (!$raw) {
        return [];
    }

    $data = json_decode($raw, true);

    return is_array($data) ? $data : [];
}

function now_datetime(): string
{
    return date('Y-m-d H:i:s');
}

function clean_str(?string $value): string
{
    return trim((string) $value);
}
