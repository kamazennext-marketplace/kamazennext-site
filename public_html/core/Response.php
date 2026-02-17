<?php

namespace Core;

class Response
{
    public static function json($data, int $status = 200): void
    {
        http_response_code($status);
        header('Content-Type: application/json');
        echo json_encode($data);
        exit;
    }

    public static function error(string $message, int $status = 400): void
    {
        self::json(['error' => $message], $status);
    }

    public static function success($data = [], int $status = 200): void
    {
        if (is_array($data)) {
            $data = array_merge(['ok' => true], $data);
        }
        self::json($data, $status);
    }
}
