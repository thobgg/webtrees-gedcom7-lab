<?php

/**
 * Create the database schema and the default data, as the setup wizard does.
 * The CLI command config-ini only writes data/config.ini.php.
 */

declare(strict_types=1);

use Fisharebest\Webtrees\DB;
use Fisharebest\Webtrees\Services\MigrationService;
use Fisharebest\Webtrees\Webtrees;

require __DIR__ . '/../vendor/autoload.php';

Webtrees::new()->bootstrap();

$config = parse_ini_file(filename: Webtrees::CONFIG_FILE);

DB::connect(
    driver: $config['dbtype'],
    host: $config['dbhost'],
    port: $config['dbport'],
    database: $config['dbname'],
    username: $config['dbuser'],
    password: $config['dbpass'],
    prefix: $config['tblpfx'],
    key: $config['dbkey'],
    certificate: $config['dbcert'],
    ca: $config['dbca'],
    verify_certificate: (bool) $config['dbverify'],
);

$migration_service = new MigrationService();
$migration_service->updateSchema('\Fisharebest\Webtrees\Schema', 'WT_SCHEMA_VERSION', Webtrees::SCHEMA_VERSION);
$migration_service->seedDatabase();

echo 'Database schema version ', Webtrees::SCHEMA_VERSION, ' with default data.', PHP_EOL;
