<?php

use LBHurtado\TruthQr\Contracts\CandidateCodes;
use LBHurtado\TruthQr\Contracts\DocumentReferences;
use LBHurtado\TruthQr\Contracts\ElectionContext;
use LBHurtado\TruthQr\Contracts\SignatureProvider;
use LBHurtado\TruthQr\Contracts\SigningKeyProvider;
use LBHurtado\TruthQr\Contracts\TrustedPublicKeyResolver;
use LBHurtado\TruthQr\Core\Base64UrlTransport;
use LBHurtado\TruthQr\Core\CanonicalJson;
use LBHurtado\TruthQr\Core\ProfileRegistry;
use LBHurtado\TruthQr\Core\RecordPayload;
use LBHurtado\TruthQr\Core\TruthQr;
use LBHurtado\TruthQr\Crypto\Ed25519RecordSigner;
use LBHurtado\TruthQr\Crypto\SigningKey;
use LBHurtado\TruthQr\Election\BallotPayloadEnvelope;
use LBHurtado\TruthQr\Election\BallotQrPayload;
use LBHurtado\TruthQr\Election\ElectionRecordDriver;
use LBHurtado\TruthQr\Election\ElectionReturnContestScopes;
use LBHurtado\TruthQr\Election\ElectionReturnPayloadEnvelope;
use LBHurtado\TruthQr\Election\ElectionReturnQrPayload;
use LBHurtado\TruthQr\Election\SignedBallotPayloadEnvelope;
use LBHurtado\TruthQr\Election\SignedBallotQrPayload;
use LBHurtado\TruthQr\Election\SignedElectionRecordDriver;
use LBHurtado\TruthQr\Election\TruthQrEnvelope;
use LBHurtado\TruthQr\Image\StandardQrCode;
use LBHurtado\TruthQr\Image\ZbarPngQrCodeDecoder;

function signedBallotHarness(): array
{
    $keypair = sodium_crypto_sign_keypair();
    $key = new SigningKey('precinct-1-ballot', sodium_crypto_sign_secretkey($keypair));
    $publicKey = sodium_crypto_sign_publickey($keypair);
    $signingKeys = new class($key) implements SigningKeyProvider
    {
        public function __construct(private readonly SigningKey $key) {}

        public function forRecord(string $purpose, string $electionId, string $precinctId): SigningKey
        {
            if ($purpose !== 'election.ballot.v2' || $electionId !== 'election-1' || $precinctId !== 'precinct-1') {
                throw new RuntimeException('No signing key for this ballot context.');
            }

            return $this->key;
        }
    };
    $trustedKeys = new class($publicKey) implements TrustedPublicKeyResolver
    {
        public bool $trusted = true;

        public function __construct(private readonly string $publicKey) {}

        public function resolve(string $keyId, string $purpose, string $electionId, string $precinctId): ?string
        {
            return $this->trusted && in_array($keyId, ['precinct-1-ballot', 'precinct-1-alias'], true) && $purpose === 'election.ballot.v2'
                && $electionId === 'election-1' && $precinctId === 'precinct-1'
                    ? $this->publicKey
                    : null;
        }
    };
    $codes = new class implements CandidateCodes
    {
        public function codesForPayload(array $payload): array
        {
            return $payload['candidate_codes'] ?? ['CAND00001'];
        }

        public function selectionsForCodes(array $codes): array
        {
            return ['president' => $codes];
        }

        public function codeTotalsForTally(array $tally): array
        {
            return ['CAND00001' => 1];
        }

        public function tallyForCodeTotals(array $codeTotals): array
        {
            return ['president' => ['CAND00001' => 1]];
        }
    };
    $documents = new class implements DocumentReferences
    {
        public function electionReturnReference(): array
        {
            return ['type' => 'election-return', 'id' => 'er-1', 'hash' => 'er-hash', 'asset_bundle_id' => 'assets-1', 'asset_bundle_hash' => 'assets-hash'];
        }
    };
    $context = new class implements ElectionContext
    {
        public string $electionId = 'election-1';

        public string $precinctId = 'precinct-1';

        public function activePrecinct(): array
        {
            return ['election_id' => $this->electionId, 'precinct_id' => $this->precinctId, 'ballot_style_id' => 'style-1', 'mapping_hash' => 'mapping-1'];
        }

        public function emlProfile(): string
        {
            return 'eml-1';
        }
    };
    $returnSigner = new class implements SignatureProvider
    {
        public function sign(string $bytes): array
        {
            return ['key_id' => 'er-1', 'public_key' => 'trusted-er-key', 'signature' => hash('sha256', $bytes)];
        }

        public function verify(string $bytes, array $signature): bool
        {
            return ($signature['public_key'] ?? null) === 'trusted-er-key'
                && hash_equals(hash('sha256', $bytes), (string) ($signature['signature'] ?? ''));
        }
    };
    $profiles = new ProfileRegistry;
    $profiles->loadYaml(dirname(__DIR__, 2).'/profiles/election.yaml');
    $profiles->loadYaml(dirname(__DIR__, 2).'/profiles/election-v2.yaml');
    $json = new CanonicalJson;
    $ballots = new BallotQrPayload($json, $codes);
    $returns = new ElectionReturnQrPayload($json, $codes, $documents, new ElectionReturnContestScopes, $context, $returnSigner);
    $truthQr = new TruthQr;
    $truthQr->register(new ElectionRecordDriver(
        $profiles, $ballots, new BallotPayloadEnvelope, $returns, new ElectionReturnPayloadEnvelope,
        new TruthQrEnvelope, $context,
    ));
    $truthQr->register(new SignedElectionRecordDriver(
        $profiles,
        new SignedBallotQrPayload($ballots, new Ed25519RecordSigner($signingKeys, $trustedKeys)),
        new SignedBallotPayloadEnvelope,
        $returns,
        new ElectionReturnPayloadEnvelope,
        new TruthQrEnvelope,
        $context,
    ));

    return [$truthQr, $trustedKeys, $context];
}

function signedBallotRecord(): array
{
    return [
        'election_id' => 'election-1',
        'precinct_id' => 'precinct-1',
        'ballot_style_id' => 'style-1',
        'mapping_hash' => 'mapping-1',
        'tabulation_profile' => 'paper-first',
        'paper_ballot_serial' => 'PRECINCT-1-000001',
        'document_profile' => ['type' => 'official-ballot', 'id' => 'ballot-1', 'hash' => 'document-hash', 'asset_bundle_id' => 'assets-1', 'asset_bundle_hash' => 'asset-hash'],
        'candidate_codes' => ['CAND00001'],
    ];
}

it('round trips a signed ballot through the opt-in driver', function (): void {
    [$truthQr] = signedBallotHarness();
    $scans = $truthQr->encode('aes-election-v2', 'ballot', signedBallotRecord());
    $typedScans = $truthQr->encodeRecord(new RecordPayload('aes-election-v2', 'ballot', signedBallotRecord()));
    $decoded = $truthQr->decode('aes-election-v2', $scans);
    $typed = $truthQr->decodeRecord('aes-election-v2', $typedScans);

    expect($scans)->toHaveCount(1)
        ->and($typedScans)->toBe($scans)
        ->and($typed->recordType)->toBe('ballot')
        ->and($typed->fields)->toBe($decoded['record'])
        ->and($typed->canonicalPayload)->toBe($decoded['canonical_payload'])
        ->and($scans[0])->toStartWith('truth://v1/waes-ballot/aes-ballot-compact-2?p=')
        ->and($decoded['record']['truth_signature_valid'])->toBeTrue()
        ->and($decoded['record']['truth_signature']['key_id'])->toBe('precinct-1-ballot')
        ->and($decoded['record']['paper_ballot_serial'])->toBe('PRECINCT-1-000001')
        ->and($decoded['record']['selections']['president'])->toBe(['CAND00001']);
});

it('rejects altered ballot fields, signature, key ID, and untrusted keys', function (): void {
    [$truthQr, $trustedKeys] = signedBallotHarness();
    $uri = $truthQr->encode('aes-election-v2', 'ballot', signedBallotRecord())[0];
    $transport = new Base64UrlTransport;
    $canonical = (new SignedBallotPayloadEnvelope)->unwrap($uri);

    foreach ([
        str_replace('CAND00001', 'CAND00002', $canonical),
        str_replace('PRECINCT-1-000001', 'PRECINCT-1-000002', $canonical),
        str_replace('precinct-1-ballot', 'unknown-key', $canonical),
        str_replace('precinct-1-ballot', 'precinct-1-alias', $canonical),
        substr($canonical, 0, -1).($canonical[-1] === 'A' ? 'B' : 'A'),
    ] as $altered) {
        $alteredUri = 'truth://v1/waes-ballot/aes-ballot-compact-2?p='.$transport->encode($altered);
        expect(fn () => $truthQr->decode('aes-election-v2', [$alteredUri]))->toThrow(RuntimeException::class);
    }

    $trustedKeys->trusted = false;
    expect(fn () => $truthQr->decode('aes-election-v2', [$uri]))->toThrow(RuntimeException::class);
});

it('rejects a cross precinct signed ballot and separates unsigned legacy ballots', function (): void {
    [$truthQr, , $context] = signedBallotHarness();
    $signed = $truthQr->encode('aes-election-v2', 'ballot', signedBallotRecord());
    $context->precinctId = 'precinct-2';

    expect(fn () => $truthQr->decode('aes-election-v2', $signed))->toThrow(RuntimeException::class);

    $context->precinctId = 'precinct-1';
    $context->electionId = 'election-2';
    expect(fn () => $truthQr->decode('aes-election-v2', $signed))->toThrow(RuntimeException::class);

    $context->electionId = 'election-1';
    $legacy = $truthQr->encode('aes-election-v1', 'ballot', signedBallotRecord());

    expect(fn () => $truthQr->decode('aes-election-v2', $legacy))->toThrow(RuntimeException::class)
        ->and(fn () => $truthQr->decode('aes-election-v1', $signed))->toThrow(RuntimeException::class)
        ->and($truthQr->decode('aes-election-v1', $legacy)['record'])->not->toHaveKey('truth_signature_valid');
});

it('preserves the existing signed election return format in the v2 profile', function (): void {
    [$truthQr] = signedBallotHarness();
    $record = [
        'election_id' => 'election-1', 'precinct_id' => 'precinct-1',
        'mapping_hash' => 'mapping-1', 'tabulation_profile' => 'paper-first',
        'accepted_ballots' => 1, 'rejected_ballots' => 0,
        'tally_hash' => 'tally-hash', 'return_hash' => 'return-hash',
        'tally' => ['president' => ['CAND00001' => 1]],
        'eml' => ['profile' => 'eml-1', 'scopes' => ['combined' => ['sha256' => 'artifact-hash', 'signing_key_id' => 'key-1']]],
    ];
    $scans = $truthQr->encode('aes-election-v2', 'election_return', $record);
    $typedScans = $truthQr->encodeRecord(new RecordPayload('aes-election-v2', 'election_return', $record));
    $decoded = $truthQr->decode('aes-election-v2', array_reverse($scans));
    $typed = $truthQr->decodeRecord('aes-election-v2', array_reverse($typedScans));

    expect($typedScans)->toBe($scans)
        ->and($typed->recordType)->toBe('election_return')
        ->and($typed->fields)->toBe($decoded['record'])
        ->and($decoded['record_type'])->toBe('election_return')
        ->and($decoded['record']['truth_signature_valid'])->toBeTrue()
        ->and($decoded['record']['tally']['president']['CAND00001'])->toBe(1);
});

it('requires complete ballot identity and document references before signing', function (): void {
    [$truthQr] = signedBallotHarness();
    $ballot = signedBallotRecord();
    unset($ballot['paper_ballot_serial']);

    expect(fn () => $truthQr->encode('aes-election-v2', 'ballot', $ballot))->toThrow(RuntimeException::class);

    $ballot = signedBallotRecord();
    unset($ballot['document_profile']);

    expect(fn () => $truthQr->encode('aes-election-v2', 'ballot', $ballot))->toThrow(RuntimeException::class);
});

it('renders and scans a dense signed ballot QR', function (): void {
    [$truthQr] = signedBallotHarness();
    $ballot = signedBallotRecord();
    $ballot['candidate_codes'] = array_map(fn (int $index): string => sprintf('CAND%05d', $index), range(1, 40));
    $uri = $truthQr->encode('aes-election-v2', 'ballot', $ballot)[0];
    $qr = new StandardQrCode(new ZbarPngQrCodeDecoder);

    expect($qr->decodePngBytes($qr->renderPrintPng($uri)))->toBe($uri)
        ->and($truthQr->decode('aes-election-v2', [$uri])['record']['truth_signature_valid'])->toBeTrue();
});
