<?php

namespace LBHurtado\TruthQr\Election;

use LBHurtado\TruthQr\Contracts\ElectionContext;
use LBHurtado\TruthQr\Contracts\RecordDriver;
use LBHurtado\TruthQr\Core\ProfileRegistry;
use RuntimeException;

final class SignedElectionRecordDriver implements RecordDriver
{
    public const ProfileId = 'aes-election-v2';

    public function __construct(
        private readonly ProfileRegistry $profiles,
        private readonly SignedBallotQrPayload $ballotPayload,
        private readonly SignedBallotPayloadEnvelope $ballotEnvelope,
        private readonly ElectionReturnQrPayload $returnPayload,
        private readonly ElectionReturnPayloadEnvelope $returnEnvelope,
        private readonly TruthQrEnvelope $envelope,
        private readonly ElectionContext $context,
    ) {}

    public function profileId(): string
    {
        return self::ProfileId;
    }

    /** @param array<string, mixed> $record
     * @return array<int, string>
     */
    public function encode(string $recordType, array $record): array
    {
        $this->assertProfile($recordType);

        return match ($recordType) {
            'ballot' => [$this->ballotEnvelope->wrap($this->ballotPayload->encode($record))],
            'election_return' => $this->returnEnvelope->wrap(
                $this->returnPayload->encode($record, (string) ($record['return_scope'] ?? 'combined')),
            ),
            default => throw new RuntimeException("Election Truth QR record type [{$recordType}] is unsupported."),
        };
    }

    /** @param array<int, string> $scans
     * @return array<string, mixed>
     */
    public function decode(array $scans): array
    {
        if ($scans === []) {
            throw new RuntimeException('No Truth QR scans were supplied.');
        }

        $metadata = $this->envelope->inspect($scans[0]);
        $type = match ($metadata['document_type']) {
            TruthQrEnvelope::BallotType => 'ballot',
            TruthQrEnvelope::ElectionReturnType => 'election_return',
            default => throw new RuntimeException('Election Truth QR document type is unsupported.'),
        };
        $this->assertProfile($type);

        if ($type === 'ballot' && ($metadata['payload_version'] ?? null) !== SignedBallotQrPayload::PayloadVersion) {
            throw new RuntimeException('Signed election profile requires a signed ballot.');
        }

        $canonical = $this->envelope->reassemble($scans);
        $record = $type === 'ballot'
            ? $this->ballotPayload->decode($canonical)
            : $this->returnPayload->decode($canonical);
        $configuration = $this->context->activePrecinct();

        foreach (['election_id', 'precinct_id', 'mapping_hash'] as $field) {
            if (isset($configuration[$field]) && ($record[$field] ?? null) !== $configuration[$field]) {
                throw new RuntimeException("Election Truth QR {$field} does not match the active precinct.");
            }
        }

        if ($type === 'ballot' && isset($configuration['ballot_style_id'])
            && ($record['ballot_style_id'] ?? null) !== $configuration['ballot_style_id']) {
            throw new RuntimeException('Election Truth QR ballot style does not match the active precinct.');
        }

        if ($type === 'election_return' && ($record['truth_signature_valid'] ?? false) !== true) {
            throw new RuntimeException('Election Truth QR signature is invalid.');
        }

        return ['record_type' => $type, 'record' => $record, 'canonical_payload' => $canonical];
    }

    private function assertProfile(string $recordType): void
    {
        $profile = $this->profiles->get(self::ProfileId);

        if (! isset($profile['records'][$recordType])) {
            throw new RuntimeException("Election Truth QR record type [{$recordType}] is unsupported.");
        }
    }
}
