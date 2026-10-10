<?php

namespace LBHurtado\TruthQr\Contracts;

interface DocumentReferences
{
    /** @return array<string, mixed> */
    public function electionReturnReference(): array;
}
