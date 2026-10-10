<?php

use LBHurtado\TruthQr\Contracts\CandidateCodes;
use LBHurtado\TruthQr\Contracts\DocumentReferences;
use LBHurtado\TruthQr\Contracts\ElectionContext;
use LBHurtado\TruthQr\Contracts\SignatureProvider;
use LBHurtado\TruthQr\Core\CanonicalJson;
use LBHurtado\TruthQr\Core\TruthQr;
use LBHurtado\TruthQr\Election\BallotPayloadEnvelope;
use LBHurtado\TruthQr\Election\BallotQrPayload;
use LBHurtado\TruthQr\Election\ElectionRecordDriver;
use LBHurtado\TruthQr\Election\ElectionReturnContestScopes;
use LBHurtado\TruthQr\Election\ElectionReturnPayloadEnvelope;
use LBHurtado\TruthQr\Election\ElectionReturnQrPayload;
use LBHurtado\TruthQr\Election\TruthQrEnvelope;

function standaloneTruthQr(): TruthQr
{
    $candidates = new class implements CandidateCodes
    {
        public function codesForPayload(array $payload): array
        {
            return ['CAND00001'];
        }

        public function selectionsForCodes(array $codes): array
        {
            return ['president' => ['candidate-1']];
        }

        public function codeTotalsForTally(array $tally): array
        {
            return ['CAND00001' => (int) ($tally['president']['candidate-1'] ?? 0)];
        }

        public function tallyForCodeTotals(array $codeTotals): array
        {
            return ['president' => ['candidate-1' => $codeTotals['CAND00001'] ?? 0]];
        }
    };
    $documents = new class implements DocumentReferences
    {
        public function electionReturnReference(): array
        {
            return ['type' => 'election-return', 'id' => 'er-1', 'hash' => 'profile-hash', 'asset_bundle_id' => 'assets-1', 'asset_bundle_hash' => 'asset-hash'];
        }
    };
    $context = new class implements ElectionContext
    {
        public function activePrecinct(): array
        {
            return ['election_id' => 'election-1', 'precinct_id' => 'precinct-1', 'mapping_hash' => 'mapping-1'];
        }

        public function emlProfile(): string
        {
            return 'eml-1';
        }
    };
    $signer = new class implements SignatureProvider
    {
        public function sign(string $bytes): array
        {
            return ['public_key' => 'local-key', 'signature' => hash('sha256', $bytes)];
        }

        public function verify(string $bytes, array $signature): bool
        {
            return ($signature['public_key'] ?? null) === 'local-key'
                && hash_equals(hash('sha256', $bytes), (string) ($signature['signature'] ?? ''));
        }
    };
    $json = new CanonicalJson;
    $truthQr = new TruthQr;
    $truthQr->register(new ElectionRecordDriver(
        electionProfileRegistry(),
        new BallotQrPayload($json, $candidates),
        new BallotPayloadEnvelope,
        new ElectionReturnQrPayload($json, $candidates, $documents, new ElectionReturnContestScopes, $context, $signer),
        new ElectionReturnPayloadEnvelope,
        new TruthQrEnvelope,
        $context,
    ));

    return $truthQr;
}

it('encodes and verifies ballot and election return records without Laravel', function (): void {
    $truthQr = standaloneTruthQr();
    $ballot = [
        'election_id' => 'election-1', 'precinct_id' => 'precinct-1',
        'ballot_style_id' => 'style-1', 'mapping_hash' => 'mapping-1',
        'tabulation_profile' => 'paper-first', 'paper_ballot_serial' => 'serial-1',
        'selections' => ['president' => ['candidate-1']],
    ];
    $ballotScans = $truthQr->encode('aes-election-v1', 'ballot', $ballot);

    expect($ballotScans)->toHaveCount(1)
        ->and($truthQr->decode('aes-election-v1', $ballotScans)['record']['selections'])->toBe($ballot['selections']);

    $return = [
        'election_id' => 'election-1', 'precinct_id' => 'precinct-1',
        'mapping_hash' => 'mapping-1', 'tabulation_profile' => 'paper-first',
        'accepted_ballots' => 2, 'rejected_ballots' => 0,
        'tally_hash' => 'tally-hash', 'return_hash' => 'return-hash',
        'tally' => ['president' => ['candidate-1' => 2]],
        'eml' => ['profile' => 'eml-1', 'scopes' => ['combined' => ['sha256' => 'artifact-hash', 'signing_key_id' => 'key-1']]],
    ];
    $returnScans = $truthQr->encode('aes-election-v1', 'election_return', $return);
    $decoded = $truthQr->decode('aes-election-v1', array_reverse($returnScans));

    expect($decoded['record']['truth_signature_valid'])->toBeTrue()
        ->and($decoded['record']['tally']['president']['candidate-1'])->toBe(2);
});

it('rejects an election record with the wrong offline mapping', function (): void {
    $truthQr = standaloneTruthQr();
    $scans = $truthQr->encode('aes-election-v1', 'ballot', [
        'election_id' => 'election-1', 'precinct_id' => 'precinct-1',
        'ballot_style_id' => 'style-1', 'mapping_hash' => 'wrong-map',
        'tabulation_profile' => 'paper-first', 'selections' => ['president' => ['candidate-1']],
    ]);

    expect(fn () => $truthQr->decode('aes-election-v1', $scans))->toThrow(RuntimeException::class);
});
