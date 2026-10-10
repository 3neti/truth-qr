<?php

namespace LBHurtado\TruthQr\Core;

use LBHurtado\TruthQr\Contracts\RecordDriver;
use RuntimeException;

final class TruthQr
{
    /** @var array<string, RecordDriver> */
    private array $drivers = [];

    public function register(RecordDriver $driver): void
    {
        $this->drivers[$driver->profileId()] = $driver;
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
