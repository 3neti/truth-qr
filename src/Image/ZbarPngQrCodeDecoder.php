<?php

namespace LBHurtado\TruthQr\Image;

use RuntimeException;
use Symfony\Component\Process\Process;

class ZbarPngQrCodeDecoder implements QrCodeDecoder
{
    public function decodePngFile(string $path): string
    {
        if (! file_exists($path)) {
            throw new RuntimeException("QR artifact [{$path}] does not exist.");
        }

        $process = new Process(['zbarimg', '--quiet', '--raw', $path]);
        $process->run();

        if (! $process->isSuccessful()) {
            throw new RuntimeException('Unable to decode QR artifact: '.$process->getErrorOutput());
        }

        return trim($process->getOutput());
    }
}
