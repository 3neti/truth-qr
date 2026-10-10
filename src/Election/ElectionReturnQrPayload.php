<?php

namespace LBHurtado\TruthQr\Election;

use BackedEnum;
use LBHurtado\TruthQr\Contracts\CandidateCodes;
use LBHurtado\TruthQr\Contracts\DocumentReferences;
use LBHurtado\TruthQr\Contracts\ElectionContext;
use LBHurtado\TruthQr\Contracts\Serializer;
use LBHurtado\TruthQr\Contracts\SignatureProvider;
use LBHurtado\TruthQr\Core\CanonicalJson;
use RuntimeException;

class ElectionReturnQrPayload implements Serializer
{
    public const PayloadVersion = 'waes-er-compact-1';

    private const CompactPrefix = self::PayloadVersion.':';

    public function __construct(
        private readonly CanonicalJson $json,
        private readonly CandidateCodes $candidateCodes,
        private readonly DocumentReferences $documents,
        private readonly ElectionReturnContestScopes $scopes,
        private readonly ElectionContext $context,
        private readonly SignatureProvider $signer,
    ) {}

    /**
     * @param  array<string, mixed>  $return
     */
    public function encode(array $return, BackedEnum|string $scope = 'combined'): string
    {
        $material = $this->compactMaterial($return, $this->scopeValue($scope));

        $signature = $this->signer->sign($this->json->encode($material));

        return self::CompactPrefix.implode('|', [
            'WAESER1',
            $this->escape((string) $material['election_id']),
            $this->escape((string) $material['precinct_id']),
            $this->escape((string) $material['return_scope']),
            $this->escape((string) $material['mapping_hash']),
            $this->escape((string) $material['tabulation_profile']),
            (string) $material['accepted_ballots'],
            (string) $material['rejected_ballots'],
            $this->escape((string) $material['tally_hash']),
            $this->escape((string) $material['return_hash']),
            $this->escape((string) ($material['document_profile']['id'] ?? '')),
            $this->escape((string) ($material['document_profile']['hash'] ?? '')),
            $this->escape((string) ($material['document_profile']['asset_bundle_id'] ?? '')),
            $this->escape((string) ($material['document_profile']['asset_bundle_hash'] ?? '')),
            $this->escape((string) ($material['eml']['profile'] ?? '')),
            $this->escape((string) ($material['eml']['artifact_sha256'] ?? '')),
            $this->escape((string) ($material['eml']['signing_key_id'] ?? '')),
            $this->encodeCodeTotals((array) $material['candidate_code_totals']),
            $this->escape((string) $signature['public_key']),
            $this->escape((string) $signature['signature']),
        ]);
    }

    /**
     * @param  array<string, mixed>  $return
     */
    public function compactHash(array $return, BackedEnum|string $scope = 'combined'): string
    {
        return $this->json->hash($this->compactMaterial($return, $this->scopeValue($scope)));
    }

    /**
     * @return array<string, mixed>
     */
    public function decode(string $payload): array
    {
        if (! str_starts_with($payload, self::CompactPrefix)) {
            throw new RuntimeException('Election return QR payload has an unsupported format.');
        }

        $parts = explode('|', substr($payload, strlen(self::CompactPrefix)), 20);

        if (! in_array(count($parts), [11, 15, 20], true) || $parts[0] !== 'WAESER1') {
            throw new RuntimeException('Compact election return QR payload is malformed.');
        }

        $candidateTotalsIndex = match (count($parts)) {
            20 => 17,
            15 => 14,
            default => 10,
        };
        $material = [
            'schema_version' => 'election-return-payload-compact-1',
            'election_id' => $this->unescape($parts[1]),
            'precinct_id' => $this->unescape($parts[2]),
            'return_scope' => $this->unescape($parts[3]),
            'mapping_hash' => $this->unescape($parts[4]),
            'tabulation_profile' => $this->unescape($parts[5]),
            'accepted_ballots' => (int) $parts[6],
            'rejected_ballots' => (int) $parts[7],
            'tally_hash' => $this->unescape($parts[8]),
            'return_hash' => $this->unescape($parts[9]),
            'candidate_code_totals' => $this->decodeCodeTotals($parts[$candidateTotalsIndex] ?? throw new RuntimeException('Compact election return QR payload is malformed.')),
        ];

        if (count($parts) === 15) {
            $material['document_profile'] = [
                'type' => 'election-return',
                'id' => $this->unescape($parts[10]),
                'hash' => $this->unescape($parts[11]),
                'asset_bundle_id' => $this->unescape($parts[12]),
                'asset_bundle_hash' => $this->unescape($parts[13]),
            ];
        }

        if (count($parts) === 20) {
            $material['document_profile'] = [
                'type' => 'election-return',
                'id' => $this->unescape($parts[10]),
                'hash' => $this->unescape($parts[11]),
                'asset_bundle_id' => $this->unescape($parts[12]),
                'asset_bundle_hash' => $this->unescape($parts[13]),
            ];
            $material['eml'] = [
                'profile' => $this->unescape($parts[14]),
                'artifact_sha256' => $this->unescape($parts[15]),
                'signing_key_id' => $this->unescape($parts[16]),
            ];
            $material['truth_signature'] = [
                'algorithm' => 'Ed25519',
                'key_id' => $material['eml']['signing_key_id'],
                'public_key' => $this->unescape($parts[18]),
                'signature' => $this->unescape($parts[19]),
            ];
        }

        $truthSignature = (array) ($material['truth_signature'] ?? []);
        unset($material['truth_signature']);
        $signatureValid = $truthSignature !== [] && $this->signer->verify($this->json->encode($material), $truthSignature);

        return [
            ...$material,
            'payload_hash' => $this->json->hash($material),
            'truth_signature' => $truthSignature,
            'truth_signature_valid' => $signatureValid,
            'tally' => $this->candidateCodes->tallyForCodeTotals((array) $material['candidate_code_totals']),
        ];
    }

    /**
     * @param  array<string, mixed>  $return
     * @return array<string, mixed>
     */
    private function compactMaterial(array $return, string $scope): array
    {
        $eml = (array) ($return['eml']['scopes'][$scope] ?? []);

        return [
            'schema_version' => 'election-return-payload-compact-1',
            'election_id' => $return['election_id'] ?? null,
            'precinct_id' => $return['precinct_id'] ?? null,
            'return_scope' => $scope,
            'mapping_hash' => $return['mapping_hash'] ?? null,
            'tabulation_profile' => $return['tabulation_profile'] ?? null,
            'accepted_ballots' => (int) ($return['accepted_ballots'] ?? 0),
            'rejected_ballots' => (int) ($return['rejected_ballots'] ?? 0),
            'tally_hash' => $return['tally_hash'] ?? null,
            'return_hash' => $return['return_hash'] ?? null,
            'document_profile' => $return['document_profile'] ?? $this->documents->electionReturnReference(),
            'eml' => [
                'profile' => $return['eml']['profile'] ?? $this->context->emlProfile(),
                'artifact_sha256' => $eml['sha256'] ?? null,
                'signing_key_id' => $eml['signing_key_id'] ?? null,
            ],
            'candidate_code_totals' => $this->candidateCodes->codeTotalsForTally(
                $this->scopedTally((array) ($return['tally'] ?? []), $scope),
            ),
        ];
    }

    /**
     * @param  array<string, array<string, int>>  $tally
     * @return array<string, array<string, int>>
     */
    private function scopedTally(array $tally, string $scope): array
    {
        if ($scope === 'combined') {
            return $tally;
        }

        $configuration = $this->scopes->configurationFor(
            $this->context->activePrecinct(),
            $scope,
        );
        $contestIds = array_fill_keys(array_filter(array_map(
            fn (array $contest): string => (string) ($contest['id'] ?? ''),
            (array) ($configuration['contests'] ?? []),
        )), true);

        return array_filter($tally, fn (string $contestId): bool => isset($contestIds[$contestId]), ARRAY_FILTER_USE_KEY);
    }

    /** @param array<string, int> $totals */
    private function encodeCodeTotals(array $totals): string
    {
        ksort($totals);

        $pairs = [];

        foreach ($totals as $code => $votes) {
            $pairs[] = $code.'='.$votes;
        }

        return implode(',', $pairs);
    }

    /** @return array<string, int> */
    private function decodeCodeTotals(string $totals): array
    {
        if ($totals === '') {
            return [];
        }

        $decoded = [];

        foreach (explode(',', $totals) as $pair) {
            $parts = explode('=', $pair, 2);

            if (count($parts) !== 2 || $parts[0] === '' || ! ctype_digit($parts[1])) {
                throw new RuntimeException('Compact election return QR vote totals are malformed.');
            }

            $decoded[$parts[0]] = (int) $parts[1];
        }

        return $decoded;
    }

    private function scopeValue(BackedEnum|string $scope): string
    {
        $value = $scope instanceof BackedEnum ? $scope->value : $scope;

        if (! in_array($value, ['combined', 'national', 'local'], true)) {
            throw new RuntimeException('Election return scope is unsupported.');
        }

        return $value;
    }

    private function escape(string $value): string
    {
        return strtr(rawurlencode($value), ['%2C' => ',', '%2D' => '-', '%2E' => '.', '%5F' => '_']);
    }

    private function unescape(string $value): string
    {
        return rawurldecode($value);
    }
}
