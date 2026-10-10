<?php

use Illuminate\Container\Container;
use LBHurtado\TruthQr\Contracts\RecordService;
use LBHurtado\TruthQr\Core\Base64UrlTransport;
use LBHurtado\TruthQr\Core\DecodedRecord;
use LBHurtado\TruthQr\Core\RecordPayload;
use LBHurtado\TruthQr\Core\ScanSession;
use LBHurtado\TruthQr\Core\TruthQr;
use LBHurtado\TruthQr\Election\BallotPayloadEnvelope;
use LBHurtado\TruthQr\Election\ElectionReturnPayloadEnvelope;
use LBHurtado\TruthQr\Election\TruthQrEnvelope;
use LBHurtado\TruthQr\Laravel\TruthQrServiceProvider;

it('loads the versioned AES election profile', function (): void {
    $profile = electionProfileRegistry()->get('aes-election-v1');

    expect($profile['records']['ballot']['marker'])->toBe('AES2')
        ->and($profile['records']['election_return']['marker'])->toBe('WAESER1');
});

it('preserves the established AES ballot wire bytes', function (): void {
    $canonical = 'aes-ballot-compact-1:AES2|election-2026|precinct-0421A|style-1|mapping-hash|device-tabulation|0421-A-000001|CAND00001,CAND00002';
    $fixture = 'truth://v1/waes-ballot/aes-ballot-compact-1?p=YWVzLWJhbGxvdC1jb21wYWN0LTE6QUVTMnxlbGVjdGlvbi0yMDI2fHByZWNpbmN0LTA0MjFBfHN0eWxlLTF8bWFwcGluZy1oYXNofGRldmljZS10YWJ1bGF0aW9ufDA0MjEtQS0wMDAwMDF8Q0FORDAwMDAxLENBTkQwMDAwMg';

    expect((new BallotPayloadEnvelope)->wrap($canonical))->toBe($fixture)
        ->and((new TruthQrEnvelope)->canonicalPayload($fixture))->toBe($canonical);
});

it('round trips transport bytes and rejects malformed encoding', function (): void {
    $transport = new Base64UrlTransport;
    $bytes = "truth\0record\n";

    expect($transport->decode($transport->encode($bytes)))->toBe($bytes)
        ->and(fn () => $transport->decode('not+base64url'))
        ->toThrow(RuntimeException::class);
});

it('tracks out of order fragments and rejects a conflicting repeat', function (): void {
    $payload = 'waes-er-compact-1:'.str_repeat('WAESER1|', 60).'CAND00001=4';
    $fragments = (new ElectionReturnPayloadEnvelope)->wrap($payload, 150);
    $session = new ScanSession(new TruthQrEnvelope);

    expect(count($fragments))->toBeGreaterThan(1);

    foreach (array_reverse($fragments) as $fragment) {
        $session->add($fragment);
    }

    expect($session->status()['complete'])->toBeTrue()
        ->and($session->reassemble())->toBe($payload);

    $session->add($fragments[0]);
    expect($session->status()['received'])->toBe(count($fragments));

    $conflict = str_replace('p=', 'p=altered', $fragments[0]);
    expect(fn () => $session->add($conflict))->toThrow(RuntimeException::class);
});

it('rejects missing, mixed, and corrupted fragments', function (): void {
    $payload = 'waes-er-compact-1:'.str_repeat('WAESER1|', 60).'CAND00001=4';
    $fragments = (new ElectionReturnPayloadEnvelope)->wrap($payload, 150);
    $envelope = new TruthQrEnvelope;

    expect(fn () => $envelope->reassemble(array_slice($fragments, 1)))->toThrow(RuntimeException::class)
        ->and(fn () => $envelope->reassemble([$fragments[0], str_replace('h=', 'h=other', $fragments[1])]))
        ->toThrow(RuntimeException::class)
        ->and(fn () => $envelope->reassemble(array_replace($fragments, [0 => str_replace('p=', 'p=altered', $fragments[0])])))->toThrow(RuntimeException::class);
});

it('rejects an election return URI with extra path segments', function (): void {
    $payload = 'waes-er-compact-1:WAESER1|fixture';
    $encoded = (new Base64UrlTransport)->encode($payload);

    expect(fn () => (new TruthQrEnvelope)->inspect("truth://v1/waes-election-return/waes-er-compact-1/extra?p={$encoded}"))
        ->toThrow(RuntimeException::class);
});

it('rejects invalid generic record payloads', function (string $profileId, string $recordType, array $fields): void {
    expect(fn () => new RecordPayload($profileId, $recordType, $fields))->toThrow(InvalidArgumentException::class);
})->with([
    [' ', 'ballot', []],
    ['election', '', []],
    ['election', 'ballot', ['unnamed']],
    ['election', 'ballot', ['value' => new stdClass]],
    ['election', 'ballot', ['nested' => [NAN]]],
]);

it('binds the default record service and permits an application override', function (): void {
    $container = new Container;
    (new TruthQrServiceProvider($container))->register();
    $default = new TruthQr;
    $container->instance(TruthQr::class, $default);

    expect($container->make(RecordService::class))->toBe($default);

    $replacement = new class implements RecordService
    {
        public function encodeRecord(RecordPayload $payload): array
        {
            return [];
        }

        public function decodeRecord(string $profileId, array $scans): DecodedRecord
        {
            throw new RuntimeException('Not used by this test.');
        }
    };
    $container->instance(RecordService::class, $replacement);

    expect($container->make(RecordService::class))->toBe($replacement);
});
