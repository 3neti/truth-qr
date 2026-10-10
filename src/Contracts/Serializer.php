<?php

namespace LBHurtado\TruthQr\Contracts;

interface Serializer
{
    /** @param array<string, mixed> $record */
    public function encode(array $record): string;

    /** @return array<string, mixed> */
    public function decode(string $bytes): array;
}
