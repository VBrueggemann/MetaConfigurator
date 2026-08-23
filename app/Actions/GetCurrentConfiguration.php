<?php

namespace App\Actions;

use App\Models\Configuration;
use App\Models\Configurator;

final class GetCurrentConfiguration
{
    public function __invoke(Configurator $configurator): Configuration
    {
        $sessionKey = "metaconfigurator.configuration.{$configurator->id}";

        if ($configurationId = session($sessionKey)) {
            $configuration = $configurator->configurations()->find($configurationId);

            if ($configuration) {
                return $configuration;
            }
        }

        $configuration = $configurator->configurations()->create();
        session([$sessionKey => $configuration->id]);

        return $configuration;
    }
}
