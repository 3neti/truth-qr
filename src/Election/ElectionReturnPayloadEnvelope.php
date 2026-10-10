<?php

namespace LBHurtado\TruthQr\Election;

use LBHurtado\TruthQr\Contracts\TransportCodec;
use LBHurtado\TruthQr\Core\Base64UrlTransport;
use RuntimeException;

class ElectionReturnPayloadEnvelope
{
    public function __construct(private readonly ?TransportCodec $transport = null) {}

    public const Scheme = 'truth';

    public const Version = 'v1';

    public const PayloadType = 'waes-election-return';

    public const PayloadVersion = ElectionReturnQrPayload::PayloadVersion;

    public const FragmentVersion = 'waes-er-fragment-1';

    private const CompactPrefix = self::PayloadVersion.':';

    /**
     * @return array<int, string>
     */
    public function wrap(string $canonicalPayload, int $maximumPayloadCharacters = 900): array
    {
        if (! str_starts_with($canonicalPayload, self::CompactPrefix)) {
            throw new RuntimeException('Only compact election return payloads can be wrapped in a truth envelope.');
        }

        $encoded = $this->base64UrlEncode($canonicalPayload);
        $complete = sprintf(
            '%s://%s/%s/%s?p=%s',
            self::Scheme,
            self::Version,
            self::PayloadType,
            self::PayloadVersion,
            $encoded,
        );

        if (strlen($complete) <= $maximumPayloadCharacters) {
            return [$complete];
        }

        $hash = hash('sha256', $canonicalPayload);
        $available = max(64, $maximumPayloadCharacters - 120);
        $chunks = str_split($encoded, $available);
        $total = count($chunks);

        return array_map(fn (string $chunk, int $index): string => sprintf(
            '%s://%s/%s/%s/%d/%d?h=%s&p=%s',
            self::Scheme,
            self::Version,
            self::PayloadType,
            self::FragmentVersion,
            $index + 1,
            $total,
            $hash,
            $chunk,
        ), $chunks, array_keys($chunks));
    }

    public function unwrap(string $payload): string
    {
        $payload = trim($payload);

        if (! str_starts_with($payload, self::Scheme.'://')) {
            return $payload;
        }

        $decoded = $this->unwrapPayload($payload);

        if (($decoded['kind'] ?? null) !== 'complete') {
            throw new RuntimeException('Election return QR fragment set must be reassembled before decoding.');
        }

        return (string) $decoded['payload'];
    }

    /**
     * @param  array<int, string>  $payloads
     */
    public function reassemble(array $payloads): string
    {
        $parts = array_map(fn (string $payload): array => $this->unwrapPayload($payload), $payloads);
        $completeParts = array_values(array_filter($parts, fn (array $part): bool => ($part['kind'] ?? null) === 'complete'));

        if ($completeParts !== []) {
            if (count($parts) !== 1) {
                throw new RuntimeException('A complete election return cannot be mixed with fragments.');
            }

            return (string) $completeParts[0]['payload'];
        }

        $first = $parts[0] ?? null;

        if (! is_array($first)) {
            throw new RuntimeException('No election return QR payloads were provided.');
        }

        $hash = (string) ($first['hash'] ?? '');
        $total = (int) ($first['total'] ?? 0);
        $chunks = [];

        foreach ($parts as $part) {
            if (($part['kind'] ?? null) !== 'fragment' || ($part['hash'] ?? null) !== $hash || (int) ($part['total'] ?? 0) !== $total) {
                throw new RuntimeException('Election return QR fragments do not belong to the same payload set.');
            }

            $index = (int) $part['index'];
            $fragment = (string) $part['chunk'];

            if (isset($chunks[$index]) && $chunks[$index] !== $fragment) {
                throw new RuntimeException('Election return QR fragment index has conflicting payloads.');
            }

            $chunks[$index] = $fragment;
        }

        ksort($chunks);

        if (count($chunks) !== $total || array_keys($chunks) !== range(1, $total)) {
            throw new RuntimeException('Election return QR fragment set is incomplete.');
        }

        $canonicalPayload = $this->base64UrlDecode(implode('', $chunks));

        if (hash('sha256', $canonicalPayload) !== $hash) {
            throw new RuntimeException('Election return QR fragment hash mismatch.');
        }

        return $canonicalPayload;
    }

    /**
     * @return array<string, mixed>
     */
    private function unwrapPayload(string $payload): array
    {
        $parts = parse_url($payload);

        if (($parts['scheme'] ?? null) !== self::Scheme || ($parts['host'] ?? null) !== self::Version) {
            throw new RuntimeException('Election return payload envelope has an unsupported truth URI header.');
        }

        $segments = explode('/', trim((string) ($parts['path'] ?? ''), '/'));

        parse_str((string) ($parts['query'] ?? ''), $query);
        $encoded = $query['p'] ?? null;

        if (! is_string($encoded) || $encoded === '') {
            throw new RuntimeException('Election return payload envelope is missing its payload.');
        }

        if ($segments === [self::PayloadType, self::PayloadVersion]) {
            $canonicalPayload = $this->base64UrlDecode($encoded);

            if (! str_starts_with($canonicalPayload, self::CompactPrefix)) {
                throw new RuntimeException('Election return payload envelope does not contain a compact payload.');
            }

            return ['kind' => 'complete', 'payload' => $canonicalPayload];
        }

        if (count($segments) === 4 && $segments[0] === self::PayloadType && $segments[1] === self::FragmentVersion) {
            return [
                'kind' => 'fragment',
                'index' => (int) $segments[2],
                'total' => (int) $segments[3],
                'hash' => is_string($query['h'] ?? null) ? $query['h'] : '',
                'chunk' => $encoded,
            ];
        }

        throw new RuntimeException('Election return payload envelope has an unsupported payload type.');
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
