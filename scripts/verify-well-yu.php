<?php
declare(strict_types=1);

// Deliberately bypass bootstrap, migrations, seeding, account setup and visit
// tracking. Default: filesystem/config checks. --database: SELECT-only checks.
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
if (array_diff(array_slice($argv, 1), ['--database']) !== []) {
    fwrite(STDERR, "Usage: php scripts/verify-well-yu.php [--database]\n");
    exit(1);
}
$root = dirname(__DIR__);
$checks = 0;
$check = static function (bool $condition, string $message) use (&$checks): void {
    if (!$condition) { throw new RuntimeException($message); }
    $checks++;
};

try {
    $config = require $root . '/app/config.php';
    $check($config['app']['name'] === 'wELYU', 'Application name must be wELYU.');
    $check($config['db']['name'] === 'well_yu_db', 'Database must be well_yu_db.');
    $check(!$config['app']['auto_migrate'] && !$config['app']['auto_seed'] && !$config['db']['auto_create'], 'Automatic database writes must be disabled.');
    $check($config['app']['url'] === 'http://localhost/WELL-YU/', 'Local URL must use /WELL-YU/.');
    $check($config['security']['session_name'] === 'WELL_YU_SESSION', 'Session name must be isolated.');
    $check($config['security']['session_cookie_path'] === '/WELL-YU/', 'Session cookie path must be isolated.');
    $check(realpath(dirname($config['security']['session_save_path'])) === realpath($root . '/data'), 'Session files must stay inside WELL-YU/data.');
    $check(!is_file($root . '/app/admin-access.local.php'), 'Review private admin setup before allowing bootstrap: it can write accounts independently of auto_seed.');
    $check(($config['mail']['smtp_host'] ?? '') === '', 'SMTP must remain unconfigured for this baseline.');
    foreach (['maps.locationiq_key', 'location.locationiq_key', 'market.alpha_vantage_key', 'news.newsapi_key', 'ai.gemini_key', 'ai.openrouter_key', 'weather.openweather_key', 'media.cloudinary.cloud_name', 'media.cloudinary.api_key', 'media.cloudinary.api_secret'] as $setting) {
        $value = $config['services'];
        foreach (explode('.', $setting) as $key) { $value = $value[$key] ?? ''; }
        $check($value === '', 'External credentials must remain blank: ' . $setting);
    }

    // Host-level thesis variables must never activate writes, links or SMTP.
    $hostSettings = ['DB_NAME' => 'isolation_probe', 'DB_PORT' => '1', 'APP_AUTO_MIGRATE' => '1', 'APP_AUTO_SEED' => '1', 'APP_URL' => 'https://isolation.invalid/', 'SMTP_HOST' => 'isolation.invalid'];
    $saved = [];
    try {
        foreach ($hostSettings as $key => $value) { $saved[$key] = getenv($key); putenv($key . '=' . $value); }
        $isolated = require $root . '/app/config.php';
        $check($isolated === $config, 'Unprefixed host environment settings leaked into WELL-YU.');
    } finally {
        foreach ($saved as $key => $value) { putenv($value === false ? $key : $key . '=' . $value); }
    }
    require_once $root . '/app/Core/Database.php';
    $rejected = false;
    try { new App\Core\Database(array_replace($config['db'], ['name' => 'isolation_probe'])); }
    catch (InvalidArgumentException) { $rejected = true; }
    $check($rejected, 'Database class must reject other database names before connecting.');

    $schema = (string) file_get_contents($root . '/database/well-yu/schema.sql');
    $expected = [];
    preg_match_all('/CREATE TABLE IF NOT EXISTS (\w+) \((.*?)^\) ENGINE=[^;]+;/ms', $schema, $tables, PREG_SET_ORDER);
    foreach ($tables as $table) {
        preg_match_all('/^  ([a-z][a-z0-9_]*)\s+[A-Z]/m', $table[2], $columns);
        $expected[$table[1]] = $columns[1];
    }
    $check(count($expected) === 35, 'Review the expected 35-table schema inventory.');
    foreach (['schema.sql', 'setup.sql', 'seed.sql'] as $file) {
        $sql = (string) file_get_contents($root . '/database/well-yu/' . $file);
        $check(!str_contains($sql, 'sfcelerate_bizstart'), 'Prepared SQL contains a thesis database reference.');
        preg_match_all('/^USE\s+`?([^`;\s]+)`?;/mi', $sql, $targets);
        $check($targets[1] !== [] && array_unique($targets[1]) === ['well_yu_db'], 'Prepared SQL must target only well_yu_db.');
        if ($file !== 'seed.sql') {
            $check(!preg_match('/^(INSERT|UPDATE|DELETE|DROP|TRUNCATE|ALTER|CREATE DATABASE)\b/mi', $sql), 'Schema setup contains unexpected data or destructive statements.');
        }
    }
    foreach (['SchemaManager', 'BrokerSchemaManager', 'V2SchemaManager', 'ClupSchemaManager'] as $manager) {
        $source = (string) file_get_contents($root . '/app/Core/' . $manager . '.php');
        preg_match_all("/<<<'SQL'\R(CREATE TABLE IF NOT EXISTS (\w+) \(.*?)\RSQL/s", $source, $definitions, PREG_SET_ORDER);
        foreach ($definitions as $definition) {
            $check(isset($expected[$definition[2]]), 'Missing manager table: ' . $definition[2]);
            preg_match_all('/^  ([a-z][a-z0-9_]*)\s+[A-Z]/m', $definition[1], $columns);
            $check(array_diff($columns[1], $expected[$definition[2]]) === [], 'Missing manager columns: ' . $definition[2]);
        }
    }
    $check(str_contains($schema, 'structured_facts_json LONGTEXT NULL') && str_contains($schema, 'proposed_activity VARCHAR(180) NULL'), 'Current assessment/intake columns are missing.');

    if (in_array('--database', $argv, true)) {
        $pdo = (new App\Core\Database($config['db']))->pdo();
        $check($pdo->query('SELECT DATABASE()')->fetchColumn() === 'well_yu_db', 'Unexpected active database.');
        $check((int) $pdo->query('SELECT 1')->fetchColumn() === 1, 'Connectivity check failed.');
        $statement = $pdo->query("SELECT TABLE_NAME, COLUMN_NAME FROM information_schema.columns WHERE table_schema = DATABASE()");
        $actual = [];
        foreach ($statement->fetchAll() as $column) { $actual[$column['TABLE_NAME']][] = $column['COLUMN_NAME']; }
        echo "Connected exclusively to well_yu_db; " . count($actual) . " tables found.\n";
        $missing = [];
        foreach ($expected as $table => $columns) {
            if (!isset($actual[$table])) { $missing[] = $table . ' (table)'; continue; }
            foreach (array_diff($columns, $actual[$table]) as $column) { $missing[] = $table . '.' . $column; }
        }
        if ($missing !== []) {
            echo 'PENDING: schema import requires explicit approval (' . count($missing) . " missing tables/columns).\n";
            echo "Read-only checks completed; no application tables or records changed.\n";
            exit(2);
        }
        // Exercise SELECTs against representative existing application tables.
        foreach (['users', 'properties', 'seller_profiles', 'criteria_config', 'clup_datasets', 'v2_scoring_configurations'] as $table) {
            echo $table . ': ' . (int) $pdo->query('SELECT COUNT(*) FROM `' . $table . '`')->fetchColumn() . " row(s).\n";
        }
    }
    echo "PASS: {$checks} isolation/schema checks. No imports, migrations, seeds, account setup or application writes executed.\n";
} catch (Throwable $error) {
    // Do not print credentials, DSNs, nested driver errors or stack traces.
    fwrite(STDERR, 'FAIL: ' . $error->getMessage() . "\n");
    exit(1);
}
