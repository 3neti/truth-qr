<?php

namespace LBHurtado\TruthQr\Crypto;

use InvalidArgumentException;

final class SigningKey
{
    public function __construct(
        public readonly string $keyId,
        private readonly string $secretKey,
    ) {
        if (preg_match('/^[A-Za-z0-9._-]{1,64}$/D', $keyId) !== 1) {
            throw new InvalidArgumentException('Signing key ID must contain 1 to 64 URL-safe characters.');
        }

        if (strlen($secretKey) !== SODIUM_CRYPTO_SIGN_SECRETKEYBYTES) {
            throw new InvalidArgumentException('Signing key must be a 64-byte Ed25519 secret key.');
        }
    }

    public function secretKey(): string
    {
        return $this->secretKey;
    }

    /** @return array{key_id: string} */
    public function __debugInfo(): array
    {
        return ['key_id' => $this->keyId];
    }
}
