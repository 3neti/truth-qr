<?php

namespace LBHurtado\TruthQr\Core;

use LBHurtado\TruthQr\Election\TruthQrEnvelope;
use RuntimeException;

final class ScanSession
{
    /** @var array<int, string> */
    private array $fragments = [];

    /** @var array<string, mixed>|null */
    private ?array $first = null;

    public function __construct(private readonly TruthQrEnvelope $envelope) {}

    /** @return array{group_id: string, total: int, received: int, missing: array<int, int>, complete: bool} */
    public function add(string $scan): array
    {
        $metadata = $this->envelope->inspect($scan);

        if ($this->first !== null && (
            $metadata['group_id'] !== $this->first['group_id']
            || $metadata['document_type'] !== $this->first['document_type']
            || $metadata['total_parts'] !== $this->first['total_parts']
        )) {
            throw new RuntimeException('Truth QR scan belongs to another payload set.');
        }

        $this->first ??= $metadata;
        $index = (int) $metadata['part_number'];

        if (isset($this->fragments[$index]) && $this->fragments[$index] !== $scan) {
            throw new RuntimeException('Truth QR scan conflicts with an existing fragment.');
        }

        $this->fragments[$index] = $scan;

        return $this->status();
    }

    /** @return array{group_id: string, total: int, received: int, missing: array<int, int>, complete: bool} */
    public function status(): array
    {
        $total = (int) ($this->first['total_parts'] ?? 0);
        $missing = array_values(array_diff(range(1, max(1, $total)), array_keys($this->fragments)));

        return [
            'group_id' => (string) ($this->first['group_id'] ?? ''),
            'total' => $total,
            'received' => count($this->fragments),
            'missing' => $total === 0 ? [] : $missing,
            'complete' => $total > 0 && $missing === [],
        ];
    }

    public function reassemble(): string
    {
        if (! $this->status()['complete']) {
            throw new RuntimeException('Truth QR scan set is incomplete.');
        }

        return $this->envelope->reassemble(array_values($this->fragments));
    }
}
