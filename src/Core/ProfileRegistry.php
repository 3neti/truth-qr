<?php

namespace LBHurtado\TruthQr\Core;

use RuntimeException;
use Symfony\Component\Yaml\Yaml;

final class ProfileRegistry
{
    /** @var array<string, array<string, mixed>> */
    private array $profiles = [];

    public function loadYaml(string $path): void
    {
        $profile = Yaml::parseFile($path);

        if (! is_array($profile) || ! is_string($profile['id'] ?? null) || ! is_array($profile['records'] ?? null)) {
            throw new RuntimeException('Truth QR profile is malformed.');
        }

        $this->profiles[$profile['id']] = $profile;
    }

    /** @return array<string, mixed> */
    public function get(string $id): array
    {
        return $this->profiles[$id] ?? throw new RuntimeException("Truth QR profile [{$id}] is not registered.");
    }
}
