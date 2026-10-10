<?php

namespace LBHurtado\TruthQr\Contracts;

interface TrustedPublicKeyResolver
{
    public function resolve(string $keyId, string $purpose, string $electionId, string $precinctId): ?string;
}
