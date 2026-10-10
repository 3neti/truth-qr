<?php

namespace LBHurtado\TruthQr\Contracts;

interface RecordDriver
{
    public function profileId(): string;

    /** @param array<string, mixed> $record
     * @return array<int, string>
     */
    public function encode(string $recordType, array $record): array;

    /** @param array<int, string> $scans
     * @return array<string, mixed>
     */
    public function decode(array $scans): array;
}
