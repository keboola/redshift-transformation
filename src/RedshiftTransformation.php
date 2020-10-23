<?php

declare(strict_types=1);

namespace Keboola\RedshiftTransformation;

use Keboola\Component\Manifest\ManifestManager;
use Keboola\Component\Manifest\ManifestManager\Options\OutTableManifestOptions;
use Keboola\Component\UserException;
use Keboola\Datatype\Definition\Exception\InvalidTypeException;
use Keboola\Datatype\Definition\GenericStorage as GenericDatatype;
use Keboola\Datatype\Definition\Redshift;
use Psr\Log\LoggerInterface;

class RedshiftTransformation
{
    private \PDO $connection;

    private LoggerInterface $logger;

    private Config $config;

    public function __construct(Config $config, LoggerInterface $logger)
    {
        $this->config = $config;
        $this->logger = $logger;
        $this->connection = $this->createConnection($config->getDatabaseConfig());
        $this->setStatementTimeout($config);
    }

    public function createManifestMetadata(array $tableNames, ManifestManager $manifestManager): void
    {
        $tableStructures = $this->getTables($tableNames);
        foreach ($tableStructures as $tableStructure) {
            $columnsMetadata = [];
            $columnNames = [];
            foreach ($tableStructure['columns'] as $column) {
                $columnNames[] = $column['name'];
                $datatypeKeys = array_flip(['length', 'nullable']);
                try {
                    $datatype = new Redshift(
                        $column['type'],
                        array_intersect_key($column, $datatypeKeys)
                    );
                } catch (InvalidTypeException $e) {
                    unset($column['length']);
                    $datatype = new GenericDatatype(
                        $column['type'],
                        array_intersect_key($column, $datatypeKeys)
                    );
                }
                $columnsMetadata[$column['name']] = $datatype->toMetadata();
            }
            unset($tableStructure['columns']);
            $tableMetadata = [];
            foreach ($tableStructure as $key => $value) {
                $tableMetadata[] = [
                    'key' => 'KBC.' . $key,
                    'value' => $value,
                ];
            }

            $tableManifestOptions = new OutTableManifestOptions();
            $tableManifestOptions
                ->setMetadata($tableMetadata)
                ->setColumns($columnNames)
                ->setColumnMetadata($columnsMetadata)
            ;
            $manifestManager->writeTableManifest($tableStructure['name'], $tableManifestOptions);
        }
    }

    public function processBlocks(array $blocks): void
    {
        foreach ($blocks as $block) {
            $this->logger->info(sprintf('Processing block "%s"', $block['name']));
            $this->processCodes($block['codes']);
        }
    }

    private function processCodes(array $codes): void
    {
        foreach ($codes as $code) {
            $this->logger->info(sprintf('Processing code "%s"', $code['name']));
            $this->executeQueries($code['name'], $code['script']);
        }
    }

    private function executeQueries(string $blockName, array $queries): void
    {
        foreach ($queries as $query) {
            $runQuery = $query;
            if ($this->config->allowQueryCleaning()) {
                $runQuery = \SqlFormatter::removeComments($runQuery);

                if (strtoupper(substr($runQuery, 0, 6)) === 'SELECT') {
                    continue;
                }
            }

            // Do not execute empty queries
            if (strlen(trim($runQuery)) === 0) {
                continue;
            }

            $this->logger->info(sprintf('Running query "%s".', $this->queryExcerpt($query)));
            try {
                $this->connection->query($runQuery);
            } catch (\Throwable $exception) {
                $message = sprintf(
                    'Query "%s" in "%s" failed with error: "%s"',
                    $this->queryExcerpt($query),
                    $blockName,
                    $exception->getMessage()
                );
                throw new UserException($message, 0, $exception);
            }
        }
    }

    private function createConnection(array $config): \PDO
    {
        $dsn = vsprintf(
            'pgsql:dbname=%s;port=%s;host=%s',
            [
                $config['database'],
                $config['port'] ?? Config::REDSHIFT_DEFAULT_PORT,
                $config['host'],
            ]
        );

        $db = new \PDO(
            $dsn,
            $config['user'],
            $config['password']
        );
        $db->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
        $db->exec(sprintf('SET search_path TO "%s";', $config['schema']));
        return $db;
    }

    private function getTables(array $tableNames): array
    {
        if (count($tableNames) === 0) {
            return [];
        }

        $sourceTables = array_map(function ($item): string {
            return $item['source'];
        }, $tableNames);

        $nameColumns = [
            'column_name',
            'table_name',
            'is_nullable',
            'data_type',
            'character_maximum_length',
            'numeric_precision',
            'numeric_scale',
        ];

        /** @var \PDOStatement $columnsQuery */
        $columnsQuery = $this->connection->query(
            $solumnSql = sprintf(
                'SELECT %s FROM information_schema.columns WHERE table_name IN (%s) ORDER BY ordinal_position',
                implode(', ', $nameColumns),
                implode(', ', array_map(function ($item) {
                    return $this->connection->quote($item);
                }, $sourceTables))
            )
        );
        /** @var array $columns */
        $columns = $columnsQuery->fetchAll();

        $tableDefs = [];
        foreach ($columns as $column) {
            if (!isset($tableDefs[$column['table_name']])) {
                $tableDefs[$column['table_name']] = [
                    'name' => $column['table_name'],
                    'columns' => [],
                ];
            }
            $tableDefs[$column['table_name']]['columns'][] = [
                'name' => $column['column_name'],
                'length' => [
                    'character_maximum' => $column['character_maximum_length'],
                    'numeric_precision' => $column['numeric_precision'],
                    'numeric_scale' => $column['numeric_scale'],
                ],
                'nullable' => (trim($column['is_nullable']) === 'NO') ? false : true,
                'type' => $column['data_type'],
            ];
        }

        $missingTables = array_diff($sourceTables, array_keys($tableDefs));
        if ($missingTables) {
            throw new UserException(
                sprintf(
                    'Tables "%s" specified in output were not created by the transformation.',
                    implode('", "', $missingTables)
                )
            );
        }

        return $tableDefs;
    }

    private function queryExcerpt(string $query): string
    {
        if (mb_strlen($query) > 1000) {
            return mb_substr($query, 0, 500, 'UTF-8') . "\n...\n" . mb_substr($query, -500, null, 'UTF-8');
        }
        return $query;
    }

    private function setStatementTimeout(Config $config): void
    {
        $this->connection->query(
            sprintf('SET statement_timeout TO %s', $config->getQueryTimeout() * 1000)
        )->execute();
    }
}
