<?php

namespace LBHurtado\TruthQr\Contracts;

interface TransportCodec
{
    public function encode(string $bytes): string;

    public function decode(string $encoded): string;
}
