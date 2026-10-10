<?php

namespace LBHurtado\TruthQr\Contracts;

interface EnvelopeCodec
{
    /** @param array<int, string> $fragments */
    public function reassemble(array $fragments): string;

    /** @return array<string, mixed> */
    public function inspect(string $fragment): array;
}
