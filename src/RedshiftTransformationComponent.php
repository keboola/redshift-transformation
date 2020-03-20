<?php

declare(strict_types=1);

namespace Keboola\RedshiftTransformation;

use Keboola\Component\BaseComponent;
use Keboola\Component\Manifest\ManifestManager;

class RedshiftTransformationComponent extends BaseComponent
{
    protected function run(): void
    {
        /** @var Config $config */
        $config = $this->getConfig();

        $redshiftTransformation = new RedshiftTransformation($config, $this->getLogger());

        $redshiftTransformation->processBlocks($config->getBlocks());

        $redshiftTransformation->createManifestMetadata(
            $config->getExpectedOutputTables(),
            new ManifestManager($this->getDataDir())
        );
    }

    protected function getConfigClass(): string
    {
        return Config::class;
    }

    protected function getConfigDefinitionClass(): string
    {
        return ConfigDefinition::class;
    }
}
