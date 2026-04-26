<?php

use Illuminate\Support\Facades\Crypt;

if (!function_exists('hash_id')) {
    function hash_id($id)
    {
        return Crypt::encryptString($id);
    }
}

if (!function_exists('unhash_id')) {
    function unhash_id($hash)
    {
        try {
            return Crypt::decryptString($hash);
        } catch (\Exception $e) {
            return null;
        }
    }
}
