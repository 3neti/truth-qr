<?php

namespace LBHurtado\TruthQr\Election;

final class ElectionReturnContestScopes
{
    /** @param array<string, mixed> $configuration
     * @return array<string, mixed>
     */
    public function configurationFor(array $configuration, string $scope): array
    {
        if ($scope === 'combined') {
            return $configuration;
        }

        $configuration['contests'] = array_values(array_filter(
            (array) ($configuration['contests'] ?? []),
            fn (array $contest): bool => $this->scopeFor($contest) === $scope,
        ));

        return $configuration;
    }

    /** @param array<string, mixed> $contest */
    public function scopeFor(array $contest): string
    {
        $key = strtolower(implode(' ', array_map('strval', [
            $contest['id'] ?? '',
            $contest['office'] ?? '',
            $contest['title'] ?? '',
        ])));
        $key = preg_replace('/\s+/', ' ', str_replace(['-', '_', '/', ','], ' ', $key)) ?? $key;

        foreach (['president', 'vice president', 'senator', 'party list', 'partylist'] as $needle) {
            if (str_contains($key, $needle)) {
                return 'national';
            }
        }

        return 'local';
    }
}
