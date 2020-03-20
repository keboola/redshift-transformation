<?php

declare(strict_types=1);

use Keboola\RedshiftTransformation\Exception\ApplicationException;

require __DIR__ . '/../../vendor/autoload.php';

$environments = [
    'REDSHIFT_HOST',
    'REDSHIFT_PORT',
    'REDSHIFT_DATABASE',
    'REDSHIFT_SCHEMA',
    'REDSHIFT_USER',
    'REDSHIFT_PASSWORD',
];

foreach ($environments as $environment) {
    if (empty(getenv($environment))) {
        throw new ApplicationException(sprintf('Missing environment "%s".', $environment));
    }
}
