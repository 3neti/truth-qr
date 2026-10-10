<?php

namespace LBHurtado\TruthQr\Contracts;

interface QrImageWriter
{
    public function renderPng(string $payload, bool $forPrint = false): string;

    public function renderSvg(string $payload): string;
}
