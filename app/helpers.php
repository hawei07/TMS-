<?php

declare(strict_types=1);

function h(mixed $value): string
{
    return htmlspecialchars((string)($value ?? ''), ENT_QUOTES, 'UTF-8');
}

function now(): string
{
    return date('Y-m-d H:i:s');
}

function utf8_strlen(?string $value): int
{
    return (int)preg_match_all('/./us', $value ?? '');
}

function json(mixed $data): never
{
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}
