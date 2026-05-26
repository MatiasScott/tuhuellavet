<?php

declare(strict_types=1);

namespace App\Core;

final class Csrf
{
    private const TOKEN_KEY = '_csrf_token';
    private const TIME_KEY = '_csrf_token_time';

    public static function token(int $ttl): string
    {
        $token = Session::get(self::TOKEN_KEY);
        $createdAt = (int) Session::get(self::TIME_KEY, 0);

        if (!is_string($token) || $token === '' || (time() - $createdAt) > $ttl) {
            $token = bin2hex(random_bytes(32));
            Session::put(self::TOKEN_KEY, $token);
            Session::put(self::TIME_KEY, time());
        }

        return $token;
    }

    public static function verify(?string $incomingToken, int $ttl): bool
    {
        $token = Session::get(self::TOKEN_KEY);
        $createdAt = (int) Session::get(self::TIME_KEY, 0);

        if (!is_string($token) || $token === '' || $incomingToken === null || (time() - $createdAt) > $ttl) {
            return false;
        }

        return hash_equals($token, $incomingToken);
    }
}
