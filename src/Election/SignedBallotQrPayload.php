<?php

namespace LBHurtado\TruthQr\Election;

use LBHurtado\TruthQr\Crypto\Ed25519RecordSigner;
use RuntimeException;

final class SignedBallotQrPayload
{
    public const PayloadVersion = 'aes-ballot-compact-2';

    private const LegacyPrefix = 'aes-ballot-compact-1:AES2';

    private const SignedPrefix = self::PayloadVersion.':AES3';

    private const Purpose = 'election.ballot.v2';

    public function __construct(
        private readonly BallotQrPayload $ballots,
        private readonly Ed25519RecordSigner $signer,
    ) {}

    /** @param array<string, mixed> $ballot */
    public function encode(array $ballot): string
    {
        $this->assertRequiredFields($ballot);
        $legacy = $this->ballots->encode($ballot);

        if (! str_starts_with($legacy, self::LegacyPrefix) || count(explode('|', $legacy)) !== 12) {
            throw new RuntimeException('Signed ballot requires a complete document profile.');
        }

        $unsigned = self::SignedPrefix.substr($legacy, strlen(self::LegacyPrefix));
        $signature = $this->signer->sign(
            self::Purpose,
            (string) $ballot['election_id'],
            (string) $ballot['precinct_id'],
            $unsigned,
        );

        return $unsigned.'|'.$signature['key_id'].'|'.$signature['signature'];
    }

    /** @return array<string, mixed> */
    public function decode(string $payload): array
    {
        $parts = explode('|', $payload);

        if (count($parts) !== 14 || $parts[0] !== self::SignedPrefix) {
            throw new RuntimeException('Signed ballot payload is malformed.');
        }

        $signature = array_pop($parts);
        $keyId = array_pop($parts);
        $unsigned = implode('|', $parts);
        $legacy = self::LegacyPrefix.substr($unsigned, strlen(self::SignedPrefix));
        $ballot = $this->ballots->decode($legacy);
        $this->assertRequiredFields($ballot);

        $this->signer->verify(
            self::Purpose,
            (string) $ballot['election_id'],
            (string) $ballot['precinct_id'],
            (string) $keyId,
            $unsigned,
            (string) $signature,
        );

        return [
            ...$ballot,
            'payload_version' => self::PayloadVersion,
            'truth_signature' => ['algorithm' => 'Ed25519', 'key_id' => $keyId, 'signature' => $signature],
            'truth_signature_valid' => true,
        ];
    }

    /** @param array<string, mixed> $ballot */
    private function assertRequiredFields(array $ballot): void
    {
        foreach (['election_id', 'precinct_id', 'ballot_style_id', 'mapping_hash', 'tabulation_profile', 'paper_ballot_serial'] as $field) {
            if (! is_string($ballot[$field] ?? null) || trim($ballot[$field]) === '') {
                throw new RuntimeException("Signed ballot requires a nonempty {$field}.");
            }
        }

        $profile = $ballot['document_profile'] ?? null;

        if (! is_array($profile)) {
            throw new RuntimeException('Signed ballot requires a document profile.');
        }

        foreach (['id', 'hash', 'asset_bundle_id', 'asset_bundle_hash'] as $field) {
            if (! is_string($profile[$field] ?? null) || trim($profile[$field]) === '') {
                throw new RuntimeException("Signed ballot document profile requires a nonempty {$field}.");
            }
        }
    }
}
