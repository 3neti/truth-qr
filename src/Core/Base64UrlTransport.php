<?php

namespace LBHurtado\TruthQr\Core;

use LBHurtado\TruthQr\Contracts\TransportCodec;
use RuntimeException;

final class Base64UrlTransport implements TransportCodec
{
    public function encode(string $bytes): string
    {
        return rtrim(strtr(base64_encode($bytes), '+/', '-_'), '=');
    }

    public function decode(string $encoded): string
    {
        if ($encoded === '' || preg_match('/^[A-Za-z0-9_-]+$/D', $encoded) !== 1) {
            throw new RuntimeException('Invalid base64url payload.');
        }

        $decoded = base64_decode(strtr($encoded, '-_', '+/').str_repeat('=', (4 - strlen($encoded) % 4) % 4), true);

        if (! is_string($decoded) || $this->encode($decoded) !== $encoded) {
            throw new RuntimeException('Invalid base64url payload.');
        }

        return $decoded;
    }
}
