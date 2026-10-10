<?php

use LBHurtado\TruthQr\Election\CandidateCodeMap;

it('maps selections and vote totals through an offline candidate manifest', function (): void {
    $manifest = ['candidates' => [
        'CAND00001' => ['candidate_id' => 'candidate-1', 'contest_id' => 'president'],
        'CAND00002' => ['candidate_id' => 'candidate-2', 'contest_id' => 'mayor'],
    ]];
    $map = new CandidateCodeMap;

    expect($map->codesForSelections($manifest, ['president' => ['candidate-1']]))->toBe(['CAND00001'])
        ->and($map->selectionsForCodes($manifest, ['CAND00001']))->toBe(['president' => ['candidate-1']])
        ->and($map->codeTotalsForTally($manifest, ['mayor' => ['candidate-2' => 3]]))->toBe(['CAND00002' => 3])
        ->and($map->tallyForCodeTotals($manifest, ['CAND00002' => 3]))->toBe(['mayor' => ['candidate-2' => 3]])
        ->and(fn () => $map->selectionsForCodes($manifest, ['unknown']))->toThrow(RuntimeException::class);
});
