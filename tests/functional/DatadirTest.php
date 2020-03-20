<?php

declare(strict_types=1);

namespace Keboola\RedshiftTransformation\FunctionalTests;

use Keboola\Csv\CsvWriter;
use Keboola\DatadirTests\AbstractDatadirTestCase;
use Keboola\DatadirTests\DatadirTestSpecificationInterface;
use Keboola\DatadirTests\DatadirTestsProviderInterface;
use Keboola\Temp\Temp;
use Symfony\Component\Filesystem\Filesystem;

class DatadirTest extends AbstractDatadirTestCase
{
    private \PDO $connection;

    private const REDSHIFT_DEFAULT_PORT = 5439;

    public function setUp(): void
    {
        parent::setUp();
        $this->connection = $this->createConnection($this->getDatabaseConfig()['workspace']);
        $this->dropTables();
    }

    /**
     * @dataProvider provideDatadirSpecifications
     */
    public function testDatadir(DatadirTestSpecificationInterface $specification): void
    {
        $tempDatadir = $this->getTempDatadir($specification);
        $this->replaceDatabaseConfig($tempDatadir);

        $this->dropTables();

        $process = $this->runScript($tempDatadir->getTmpFolder());

        $this->dumpTables($tempDatadir->getTmpFolder());

        $this->assertMatchesSpecification($specification, $process, $tempDatadir->getTmpFolder());
    }

    /**
     * @return DatadirTestsProviderInterface[]
     */
    protected function getDataProviders(): array
    {
        return [
            new DatadirTestProvider($this->getTestFileDir()),
        ];
    }

    private function replaceDatabaseConfig(Temp $tempDatadir): void
    {
        $configFile = $tempDatadir->getTmpFolder() . '/config.json';
        $config = (string) file_get_contents($configFile);
        $newConfig = array_merge(
            json_decode($config, true),
            [
                'authorization' => [
                    'workspace' => [
                        'host' => getenv('REDSHIFT_HOST'),
                        'port' => getenv('REDSHIFT_PORT'),
                        'database' => getenv('REDSHIFT_DATABASE'),
                        'schema' => getenv('REDSHIFT_SCHEMA'),
                        'user' => getenv('REDSHIFT_USER'),
                        'password' => getenv('REDSHIFT_PASSWORD'),
                    ]
                ]
            ]
        );
        file_put_contents($configFile, json_encode($newConfig));
    }

    private function dropTables(): void
    {
        foreach ($this->getTableNames() as $tableName) {
            $this->connection->query(sprintf('DROP TABLE IF EXISTS "%s"', $tableName))->execute();
        }
    }

    private function dumpTables(string $tmpFolder): void
    {
        $dumpDir = $tmpFolder . '/out/db-dump';
        $fs = new Filesystem();
        $fs->mkdir($dumpDir, 0777);

        foreach ($this->getTableNames() as $tableName) {
            $this->dumpTableData($tableName, $dumpDir);
        }
    }

    private function getTableNames(): array
    {
        $tables = $this->connection->query(
            'SELECT table_name FROM information_schema.tables WHERE table_schema = \'public\';'
        )->fetchAll(\PDO::FETCH_ASSOC);

        $tableNames = array_map(function ($item) {
            return $item['table_name'];
        }, $tables);

        return $tableNames;
    }

    private function dumpTableData(string $tableName, string $tmpFolder): void
    {
        $csvDumpFile = new CsvWriter(sprintf('%s/%s.csv', $tmpFolder, $tableName));

        $rows = $this->connection->query(sprintf('SELECT * FROM %s', $tableName))->fetchAll(\PDO::FETCH_ASSOC);

        if ($rows) {
            $csvDumpFile->writeRow(array_keys(current($rows)));
            foreach ($rows as $row) {
                $csvDumpFile->writeRow($row);
            }
        }
    }

    private function createConnection(array $config): \PDO
    {
        $dsn = vsprintf(
            'pgsql:dbname=%s;port=%s;host=%s',
            [
                $config['database'],
                $config['port'],
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

    private function getDatabaseConfig(): array
    {
        return [
            'workspace' => [
                'host' => getenv('REDSHIFT_HOST'),
                'port' => self::REDSHIFT_DEFAULT_PORT,
                'database' => getenv('REDSHIFT_DATABASE'),
                'schema' => getenv('REDSHIFT_SCHEMA'),
                'user' => getenv('REDSHIFT_USER'),
                'password' => getenv('REDSHIFT_PASSWORD'),
            ],
        ];
    }
}
