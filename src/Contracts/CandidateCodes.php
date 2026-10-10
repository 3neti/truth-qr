<?php

namespace LBHurtado\TruthQr\Contracts;

interface CandidateCodes
{
    /** @param array<string, mixed> $payload
     * @return array<int, string>
     */
    public function codesForPayload(array $payload): array;

    /** @param array<int, string> $codes
     * @return array<string, array<int, string>>
     */
    public function selectionsForCodes(array $codes): array;

    /** @param array<string, array<string, int>> $tally
     * @return array<string, int>
     */
    public function codeTotalsForTally(array $tally): array;

    /** @param array<string, int> $codeTotals
     * @return array<string, array<string, int>>
     */
    public function tallyForCodeTotals(array $codeTotals): array;
}
