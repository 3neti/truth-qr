<?php

namespace LBHurtado\TruthQr\Contracts;

interface SignatureProvider
{
    /** @return array<string, string> */
    public function sign(string $bytes): array;

    /** @param array<string, mixed> $signature */
    public function verify(string $bytes, array $signature): bool;
}
