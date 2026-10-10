<?php

namespace LBHurtado\TruthQr\Core;

use LBHurtado\TruthQr\Contracts\RecordDriver;
use LBHurtado\TruthQr\Contracts\RecordService;
use RuntimeException;

final class TruthQr implements RecordService
{
    /** @var array<string, RecordDriver> */
    private array $drivers = [];

    public function register(RecordDriver $driver): void
    {
        $this->drivers[$driver->profileId()] = $driver;
    }

    /** @return array<int, string> */
    public function encodeRecord(RecordPayload $payload): array
    {
        return $this->encode($payload->profileId, $payload->recordType, $payload->fields);
    }

    /** @param array<int, string> $scans */
    public function decodeRecord(string $profileId, array $scans): DecodedRecord
    {
        $decoded = $this->decode($profileId, $scans);

        if (! is_string($decoded['record_type'] ?? null)
            || ! is_array($decoded['record'] ?? null)
            || ! is_string($decoded['canonical_payload'] ?? null)) {
            throw new RuntimeException('Truth QR driver returned a malformed decoded record.');
        }

        return new DecodedRecord($profileId, $decoded['record_type'], $decoded['record'], $decoded['canonical_payload']);
    }

    /** @param array<string, mixed> $record
     * @return array<int, string>
     */
    public function encode(string $profileId, string $recordType, array $record): array
    {
        return $this->driver($profileId)->encode($recordType, $record);
    }

    /** @param array<int, string> $scans
     * @return array<string, mixed>
     */
    public function decode(string $profileId, array $scans): array
    {
        return $this->driver($profileId)->decode($scans);
    }

    private function driver(string $profileId): RecordDriver
    {
        return $this->drivers[$profileId] ?? throw new RuntimeException("Truth QR driver [{$profileId}] is not registered.");
    }
}
