<?php

namespace LBHurtado\TruthQr\Core;

final readonly class DecodedRecord
{
    /** @param array<string, mixed> $fields */
    public function __construct(
        public string $profileId,
        public string $recordType,
        public array $fields,
        public string $canonicalPayload,
    ) {}
}
