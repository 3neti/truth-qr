<?php

namespace LBHurtado\TruthQr\Image;

use BaconQrCode\Common\ErrorCorrectionLevel;
use BaconQrCode\Renderer\Image\ImagickImageBackEnd;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use LBHurtado\TruthQr\Contracts\QrImageWriter;
use RuntimeException;

class StandardQrCode implements QrImageWriter
{
    private const OutputSize = 740;

    private const PrintOutputSize = 1080;

    private const MarginModules = 4;

    public function __construct(
        private readonly QrCodeDecoder $decoder,
    ) {}

    public function renderPng(string $payload, bool $forPrint = false): string
    {
        return $forPrint ? $this->renderPrintPng($payload) : $this->renderPngAtSize($payload, self::OutputSize);
    }

    public function renderPrintPng(string $payload): string
    {
        return $this->renderPngAtSize($payload, self::PrintOutputSize, ErrorCorrectionLevel::Q());
    }

    public function renderSvg(string $payload): string
    {
        $renderer = new ImageRenderer(
            new RendererStyle(self::PrintOutputSize, self::MarginModules),
            new SvgImageBackEnd,
        );

        return (new Writer($renderer))->writeString($payload, ecLevel: ErrorCorrectionLevel::Q());
    }

    private function renderPngAtSize(string $payload, int $size, ?ErrorCorrectionLevel $errorCorrectionLevel = null): string
    {
        $renderer = new ImageRenderer(
            new RendererStyle($size, self::MarginModules),
            new ImagickImageBackEnd('png'),
        );

        return (new Writer($renderer))->writeString($payload, ecLevel: $errorCorrectionLevel);
    }

    public function decodePngFile(string $path): string
    {
        return $this->decoder->decodePngFile($path);
    }

    public function decodePngBytes(string $contents): string
    {
        $path = tempnam(sys_get_temp_dir(), 'aes-qr-');

        if ($path === false) {
            throw new RuntimeException('Unable to create temporary QR decode file.');
        }

        $pngPath = $path.'.png';
        rename($path, $pngPath);
        file_put_contents($pngPath, $contents);

        try {
            return $this->decodePngFile($pngPath);
        } finally {
            @unlink($pngPath);
        }
    }
}
