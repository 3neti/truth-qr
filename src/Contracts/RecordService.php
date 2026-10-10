<?php

namespace LBHurtado\TruthQr\Contracts;

use LBHurtado\TruthQr\Core\DecodedRecord;
use LBHurtado\TruthQr\Core\RecordPayload;

interface RecordService
{
    /** @return array<int, string> */
    public function encodeRecord(RecordPayload $payload): array;

    /** @param array<int, string> $scans */
    public function decodeRecord(string $profileId, array $scans): DecodedRecord;
}
