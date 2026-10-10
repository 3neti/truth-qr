<?php

namespace LBHurtado\TruthQr\Election;

use LBHurtado\TruthQr\Contracts\TransportCodec;
use LBHurtado\TruthQr\Core\Base64UrlTransport;
use RuntimeException;

class BallotPayloadEnvelope
{
    public function __construct(private readonly ?TransportCodec $transport = null) {}

    public const Scheme = 'truth';

    public const Version = 'v1';

    public const PayloadType = 'waes-ballot';

    /** @var array<int, string> */
    private const LegacyPayloadTypes = ['vaes-ballot'];

    public const PayloadVersion = 'aes-ballot-compact-1';

    private const CompactPrefix = self::PayloadVersion.':';

    public function wrap(string $canonicalPayload): string
    {
        if (! str_starts_with($canonicalPayload, self::CompactPrefix)) {
            throw new RuntimeException('Only compact ballot payloads can be wrapped in a truth envelope.');
        }

        return sprintf(
            '%s://%s/%s/%s?p=%s',
            self::Scheme,
            self::Version,
            self::PayloadType,
            self::PayloadVersion,
            $this->base64UrlEncode($canonicalPayload),
        );
    }

    public function unwrap(string $payload): string
    {
        $payload = trim($payload);

        if (! str_starts_with($payload, self::Scheme.'://')) {
            return $payload;
        }

        $parts = parse_url($payload);

        if (($parts['scheme'] ?? null) !== self::Scheme) {
            throw new RuntimeException('Ballot payload envelope has an unsupported scheme.');
        }

        if (($parts['host'] ?? null) !== self::Version) {
            throw new RuntimeException('Ballot payload envelope has an unsupported version.');
        }

        $path = trim((string) ($parts['path'] ?? ''), '/');
        $segments = $path === '' ? [] : explode('/', $path);

        if (
            count($segments) !== 2
            || ! in_array($segments[0], [self::PayloadType, ...self::LegacyPayloadTypes], true)
            || $segments[1] !== self::PayloadVersion
        ) {
            throw new RuntimeException('Ballot payload envelope has an unsupported payload type.');
        }

        parse_str((string) ($parts['query'] ?? ''), $query);
        $encoded = $query['p'] ?? null;

        if (! is_string($encoded) || $encoded === '') {
            throw new RuntimeException('Ballot payload envelope is missing its ballot payload.');
        }

        $canonicalPayload = $this->base64UrlDecode($encoded);

        if (! str_starts_with($canonicalPayload, self::CompactPrefix)) {
            throw new RuntimeException('Ballot payload envelope does not contain a compact ballot payload.');
        }

        return $canonicalPayload;
    }

    public function payloadVersion(string $payload): string
    {
        $canonicalPayload = $this->unwrap($payload);

        if (str_starts_with($canonicalPayload, self::CompactPrefix)) {
            return self::PayloadVersion;
        }

        if (str_starts_with($canonicalPayload, 'aes-ballot-zlib-1:')) {
            return 'aes-ballot-zlib-1';
        }

        return 'legacy-json';
    }

    private function base64UrlEncode(string $payload): string
    {
        return ($this->transport ?? new Base64UrlTransport)->encode($payload);
    }

    private function base64UrlDecode(string $payload): string
    {
        return ($this->transport ?? new Base64UrlTransport)->decode($payload);
    }
}
