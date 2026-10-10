<?php

namespace LBHurtado\TruthQr\Crypto;

use LBHurtado\TruthQr\Contracts\SigningKeyProvider;
use LBHurtado\TruthQr\Contracts\TrustedPublicKeyResolver;
use LBHurtado\TruthQr\Core\Base64UrlTransport;
use RuntimeException;

final class Ed25519RecordSigner
{
    public function __construct(
        private readonly SigningKeyProvider $signingKeys,
        private readonly TrustedPublicKeyResolver $trustedKeys,
        private readonly Base64UrlTransport $transport = new Base64UrlTransport,
    ) {}

    /** @return array{key_id: string, signature: string} */
    public function sign(string $purpose, string $electionId, string $precinctId, string $bytes): array
    {
        $key = $this->signingKeys->forRecord($purpose, $electionId, $precinctId);
        $signature = sodium_crypto_sign_detached($this->message($purpose, $bytes, $key->keyId), $key->secretKey());

        return ['key_id' => $key->keyId, 'signature' => $this->transport->encode($signature)];
    }

    public function verify(
        string $purpose,
        string $electionId,
        string $precinctId,
        string $keyId,
        string $bytes,
        string $signature,
    ): void {
        if (preg_match('/^[A-Za-z0-9._-]{1,64}$/D', $keyId) !== 1) {
            throw new RuntimeException('Truth QR signing key ID is malformed.');
        }

        $publicKey = $this->trustedKeys->resolve($keyId, $purpose, $electionId, $precinctId);

        if ($publicKey === null || strlen($publicKey) !== SODIUM_CRYPTO_SIGN_PUBLICKEYBYTES) {
            throw new RuntimeException('Truth QR signing key is not trusted for this record.');
        }

        $detached = $this->transport->decode($signature);

        if (strlen($detached) !== SODIUM_CRYPTO_SIGN_BYTES
            || ! sodium_crypto_sign_verify_detached($detached, $this->message($purpose, $bytes, $keyId), $publicKey)) {
            throw new RuntimeException('Truth QR record signature is invalid.');
        }
    }

    private function message(string $purpose, string $bytes, string $keyId): string
    {
        return "truth-qr\x00".$purpose."\x00".$bytes.'|'.$keyId;
    }
}
