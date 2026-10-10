<?php

namespace LBHurtado\TruthQr\Contracts;

use LBHurtado\TruthQr\Crypto\SigningKey;

interface SigningKeyProvider
{
    public function forRecord(string $purpose, string $electionId, string $precinctId): SigningKey;
}
