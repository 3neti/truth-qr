<?php

namespace LBHurtado\TruthQr\Contracts;

interface ElectionContext
{
    /** @return array<string, mixed> */
    public function activePrecinct(): array;

    public function emlProfile(): string;
}
