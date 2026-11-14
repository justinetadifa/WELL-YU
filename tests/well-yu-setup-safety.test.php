<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
define('WELL_YU_INSPECTION_FUNCTIONS_ONLY', true);
require dirname(__DIR__) . '/scripts/inspect-well-yu-database.php';

$checks = 0;
$check = static function (bool $condition, string $message) use (&$checks): void {
    if (!$condition) { throw new RuntimeException($message); }
    $checks++;
};
$schema = (string) file_get_contents(dirname(__DIR__) . '/database/well-yu/schema.sql');
preg_match_all('/CREATE TABLE IF NOT EXISTS (\w+) \((.*?)^\) ENGINE=[^;]+;/ms', $schema, $matches, PREG_SET_ORDER);
$expected = [];
foreach ($matches as $match) { $expected[$match[1]] = ['ddl' => $match[0]]; }
$check(count($expected) === 35, '35-table inventory required.');

$emptyPlan = wy_inspection_plan($expected, [], []);
$check(substr_count($emptyPlan, 'CREATE TABLE IF NOT EXISTS ') === 35, 'Empty database needs all tables.');
$check(!preg_match('/^(INSERT|UPDATE|DELETE|REPLACE|DROP|TRUNCATE|ALTER)\b/m', $emptyPlan), 'Plan must never change existing rows or tables.');
$check(substr_count($emptyPlan, 'USE `well_yu_db`;') === 1, 'Single WELL-YU database selection required.');

// Simulate an interrupted schema import after an arbitrary subset of tables.
// Every existing definition must be skipped, regardless of records it contains.
$existing = ['users', 'seller_profiles', 'properties', 'clup_datasets', 'clup_zones'];
$partialPlan = wy_inspection_plan($expected, $existing, []);
$check(substr_count($partialPlan, 'CREATE TABLE IF NOT EXISTS ') === 30, 'Partial setup must create only missing tables.');
foreach ($existing as $name) { $check(!str_contains($partialPlan, 'CREATE TABLE IF NOT EXISTS ' . $name . ' ('), 'Existing table must be preserved: ' . $name); }

// A second inspection after approved completion produces a no-op plan.
$completePlan = wy_inspection_plan($expected, array_keys($expected), []);
$check(!str_contains($completePlan, 'CREATE TABLE'), 'Completed schema needs no DDL.');
$blocked = wy_inspection_plan($expected, $existing, ['Missing users.email']);
$check(str_contains($blocked, 'BLOCKED') && !str_contains($blocked, 'CREATE TABLE'), 'Incompatible existing schema must block completion DDL.');
$brokenOrder = array_reverse($expected, true);
$rejected = false;
try { wy_inspection_plan($brokenOrder, [], []); }
catch (RuntimeException) { $rejected = true; }
$check($rejected, 'Missing parent-table order must fail closed.');
$check(wy_inspection_type('int(11)') === wy_inspection_type('INT'), 'MariaDB integer display widths must normalize.');
$check(wy_inspection_type('INT UNSIGNED') !== wy_inspection_type('INT'), 'Signedness must remain significant.');
$check(wy_inspection_type('int(10) unsigned') === wy_inspection_type('INT UNSIGNED'), 'Integer width normalization must preserve unsigned separation.');
$check(wy_inspection_type('DECIMAL(12,4)') !== wy_inspection_type('DECIMAL(12,2)'), 'Numeric precision must remain significant.');
$check(wy_inspection_type('DECIMAL(10, 6)') === wy_inspection_type('decimal(10,6)'), 'Decimal formatting must not create a false schema conflict.');
$check(wy_inspection_type('DECIMAL ( 12 , 4 )') === wy_inspection_type('DECIMAL(12,4)'), 'Type-parameter whitespace must normalize.');

// Invalid SQL must fail before a connection or statement can be used.
final class WellYuOfflinePdo extends PDO { public function __construct() {} }
foreach (['INSERT INTO users VALUES (1)', 'UPDATE users SET id = 1', 'DELETE FROM users', 'DROP TABLE users', 'SELECT 1; SELECT 2'] as $sql) {
    $rejected = false;
    try { wy_inspection_query(new WellYuOfflinePdo(), $sql); }
    catch (RuntimeException) { $rejected = true; }
    $check($rejected, 'Inspector must reject unsupported statements.');
}
echo "WELL-YU setup safety: {$checks} checks passed; no database connection or SQL execution.\n";
