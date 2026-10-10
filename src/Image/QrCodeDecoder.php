<?php

namespace LBHurtado\TruthQr\Image;

interface QrCodeDecoder
{
    public function decodePngFile(string $path): string;
}
