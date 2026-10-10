<?php

namespace LBHurtado\TruthQr\Election;

use LBHurtado\TruthQr\Core\Base64UrlTransport;
use RuntimeException;

final class SignedBallotPayloadEnvelope
{
    public const PayloadVersion = 'aes-ballot-compact-2';

    public function __construct(private readonly Base64UrlTransport $transport = new Base64UrlTransport) {}

    public function wrap(string $canonicalPayload): string
    {
        if (! str_starts_with($canonicalPayload, self::PayloadVersion.':')) {
            throw new RuntimeException('Only signed ballot payloads can be wrapped in this envelope.');
        }

        return 'truth://v1/waes-ballot/'.self::PayloadVersion.'?p='.$this->transport->encode($canonicalPayload);
    }

    public function unwrap(string $uri): string
    {
        $parts = parse_url(trim($uri));

        if (! is_array($parts) || ($parts['scheme'] ?? null) !== 'truth' || ($parts['host'] ?? null) !== 'v1'
            || ($parts['path'] ?? null) !== '/waes-ballot/'.self::PayloadVersion) {
            throw new RuntimeException('Signed ballot envelope has an unsupported header.');
        }

        parse_str((string) ($parts['query'] ?? ''), $query);
        $encoded = $query['p'] ?? null;

        if (! is_string($encoded)) {
            throw new RuntimeException('Signed ballot envelope is missing its payload.');
        }

        $canonical = $this->transport->decode($encoded);

        if (! str_starts_with($canonical, self::PayloadVersion.':')) {
            throw new RuntimeException('Signed ballot envelope payload version does not match.');
        }

        return $canonical;
    }
}
