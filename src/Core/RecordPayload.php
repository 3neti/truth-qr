<?php

namespace LBHurtado\TruthQr\Core;

use InvalidArgumentException;

final readonly class RecordPayload
{
    /** @param array<string, mixed> $fields */
    public function __construct(
        public string $profileId,
        public string $recordType,
        public array $fields,
    ) {
        if (trim($profileId) === '' || trim($recordType) === '') {
            throw new InvalidArgumentException('Truth QR profile ID and record type are required.');
        }

        if ($fields !== [] && array_is_list($fields)) {
            throw new InvalidArgumentException('Truth QR record fields must be a keyed array.');
        }

        $this->assertJsonValues($fields);
    }

    /** @param array<int|string, mixed> $values */
    private function assertJsonValues(array $values): void
    {
        foreach ($values as $value) {
            if (is_array($value)) {
                $this->assertJsonValues($value);

                continue;
            }

            if ($value === null || is_string($value) || is_int($value) || is_bool($value)
                || (is_float($value) && is_finite($value))) {
                continue;
            }

            throw new InvalidArgumentException('Truth QR record fields must contain only JSON-compatible values.');
        }
    }
}
