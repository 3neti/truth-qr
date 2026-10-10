<?php

namespace LBHurtado\TruthQr\Election;

use RuntimeException;

final class CandidateCodeMap
{
    /** @param array<string, mixed> $manifest
     * @param  array<string, array<int, string>>  $selections
     * @return array<int, string>
     */
    public function codesForSelections(array $manifest, array $selections): array
    {
        $codesByCandidateId = [];

        foreach ((array) ($manifest['candidates'] ?? []) as $code => $candidate) {
            $codesByCandidateId[(string) $candidate['candidate_id']] = (string) $code;
        }

        $codes = [];

        foreach ($selections as $candidateIds) {
            foreach ($candidateIds as $candidateId) {
                $code = $codesByCandidateId[(string) $candidateId] ?? null;

                if ($code === null) {
                    throw new RuntimeException("Candidate [{$candidateId}] has no compact QR code mapping.");
                }

                $codes[] = $code;
            }
        }

        return $codes;
    }

    /** @param array<string, mixed> $manifest
     * @param  array<int, string>  $codes
     * @return array<string, array<int, string>>
     */
    public function selectionsForCodes(array $manifest, array $codes): array
    {
        $candidates = (array) ($manifest['candidates'] ?? []);
        $selections = [];

        foreach ($codes as $code) {
            $candidate = $candidates[$code] ?? null;

            if (! is_array($candidate)) {
                throw new RuntimeException("Candidate code [{$code}] is not present in this precinct mapping.");
            }

            $contestId = (string) $candidate['contest_id'];
            $selections[$contestId] ??= [];
            $selections[$contestId][] = (string) $candidate['candidate_id'];
        }

        return $selections;
    }

    /** @param array<string, mixed> $manifest
     * @param  array<string, array<string, int>>  $tally
     * @return array<string, int>
     */
    public function codeTotalsForTally(array $manifest, array $tally): array
    {
        $codesByCandidateId = [];

        foreach ((array) ($manifest['candidates'] ?? []) as $code => $candidate) {
            $codesByCandidateId[(string) $candidate['candidate_id']] = (string) $code;
        }

        $totals = [];

        foreach ($tally as $candidateTotals) {
            foreach ($candidateTotals as $candidateId => $votes) {
                $code = $codesByCandidateId[(string) $candidateId] ?? null;

                if ($code === null) {
                    throw new RuntimeException("Candidate [{$candidateId}] has no compact QR code mapping.");
                }

                if ((int) $votes > 0) {
                    $totals[$code] = (int) $votes;
                }
            }
        }

        ksort($totals);

        return $totals;
    }

    /** @param array<string, mixed> $manifest
     * @param  array<string, int>  $codeTotals
     * @return array<string, array<string, int>>
     */
    public function tallyForCodeTotals(array $manifest, array $codeTotals): array
    {
        $candidates = (array) ($manifest['candidates'] ?? []);
        $tally = [];

        foreach ($codeTotals as $code => $votes) {
            $candidate = $candidates[$code] ?? null;

            if (! is_array($candidate)) {
                throw new RuntimeException("Candidate code [{$code}] is not present in this precinct mapping.");
            }

            $contestId = (string) $candidate['contest_id'];
            $candidateId = (string) $candidate['candidate_id'];
            $tally[$contestId] ??= [];
            $tally[$contestId][$candidateId] = (int) $votes;
        }

        return $tally;
    }
}
