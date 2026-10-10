<?php

namespace LBHurtado\TruthQr\Laravel;

use Illuminate\Support\ServiceProvider;
use LBHurtado\TruthQr\Contracts\RecordService;
use LBHurtado\TruthQr\Core\ProfileRegistry;
use LBHurtado\TruthQr\Core\TruthQr;
use LBHurtado\TruthQr\Election\ElectionRecordDriver;
use LBHurtado\TruthQr\Image\QrCodeDecoder;
use LBHurtado\TruthQr\Image\ZbarPngQrCodeDecoder;

final class TruthQrServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(ProfileRegistry::class, function (): ProfileRegistry {
            $registry = new ProfileRegistry;
            $registry->loadYaml(dirname(__DIR__, 2).'/profiles/election.yaml');
            $registry->loadYaml(dirname(__DIR__, 2).'/profiles/election-v2.yaml');

            return $registry;
        });
        $this->app->bind(QrCodeDecoder::class, ZbarPngQrCodeDecoder::class);
        $this->app->singleton(TruthQr::class, function ($app): TruthQr {
            $truthQr = new TruthQr;
            $truthQr->register($app->make(ElectionRecordDriver::class));

            return $truthQr;
        });
        $this->app->bind(RecordService::class, fn ($app): RecordService => $app->make(TruthQr::class));
    }
}
