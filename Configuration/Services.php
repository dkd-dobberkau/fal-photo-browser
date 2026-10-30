<?php

declare(strict_types=1);

use DkdDobberkau\FalPhotoBrowser\Service\AimClient;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

return static function (ContainerConfigurator $configurator): void {
    if (class_exists(\B13\Aim\Ai::class)) {
        $configurator->services()->set(AimClient::class)->autowire();
    }
};
