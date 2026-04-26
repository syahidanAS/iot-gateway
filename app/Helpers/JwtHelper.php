<?php

use Firebase\JWT\JWT;
use Firebase\JWT\Key;

if (!function_exists('generate_jwt')) {
    function generate_jwt($user)
    {
        $payload = [
            'iss' => config('app.url'),
            'sub' => $user->id,
            'email' => $user->email,
            'iat' => time(),
            'exp' => time() + 60 * 60 * 24 // 1 hari
        ];

        return JWT::encode($payload, env('JWT_SECRET'), env('JWT_ALGO'));
    }
}

if (!function_exists('verify_jwt')) {
    function verify_jwt($token)
    {
        try {
            return JWT::decode($token, new Key(env('JWT_SECRET'), env('JWT_ALGO')));
        } catch (\Exception $e) {
            return null;
        }
    }
}
