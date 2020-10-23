<?php

declare(strict_types=1);

namespace Keboola\RedshiftTransformation;

use Keboola\Component\Config\BaseConfig;
use Keboola\RedshiftTransformation\Exception\ApplicationException;

class Config extends BaseConfig
{
    public const REDSHIFT_DEFAULT_PORT = 5439;

    public function getQueryTimeout(): int
    {
        return (int) $this->getValue(['parameters', 'query_timeout']);
    }

    public function allowQueryCleaning(): bool
    {
        return (bool) $this->getValue(['parameters', 'allow_query_cleaning']);
    }

    public function getBlocks(): array
    {
        return $this->getValue(['parameters', 'blocks']);
    }

    public function getDatabaseConfig(): array
    {
        try {
            return $this->getValue(['authorization', 'workspace']);
        } catch (\InvalidArgumentException $exception) {
            throw new ApplicationException('Missing authorization for workspace');
        }
    }
}
