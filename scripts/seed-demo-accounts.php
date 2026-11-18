<?php
declare(strict_types=1);

/**
 * Idempotent CLI Seeder for wELYU Pre-Verified Demo Accounts & Rich Role Data.
 *
 * Usage:
 *   php scripts/seed-demo-accounts.php
 *
 * SAFETY GUARANTEES:
 * - CLI only (never executes via HTTP directly).
 * - Verifies `well_yu_db` isolation and keeps `auto_migrate = false` & `auto_seed = false` in `app/config.php`.
 */
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

$root = dirname(__DIR__);
$config = require $root . '/app/config.php';

if (($config['db']['name'] ?? '') !== 'well_yu_db'
    || !empty($config['app']['auto_migrate'])
    || !empty($config['app']['auto_seed'])
    || !empty($config['db']['auto_create'])) {
    fwrite(STDERR, "Refusing to run: well_yu_db isolation or disabled-automation safety check failed.\n");
    exit(1);
}

require_once $root . '/app/Support/DemoAccountService.php';

$args = array_slice($argv, 1);
$dryRun = in_array('--dry-run', $args, true);

$definitions = \App\Support\DemoAccountService::accountDefinitions();

echo "================================================================================\n";
echo " wELYU · SIKLAB 2026 Pre-Verified Demo Accounts & Rich Role Data Seeder\n";
echo "================================================================================\n";
echo "Target Database : " . $config['db']['name'] . "\n";
echo "Execution Mode  : " . ($dryRun ? "DRY-RUN (preview only)" : "LIVE UPSERT (idempotent)") . "\n\n";

if (!$dryRun) {
    $pdo = sfc_app_container()['pdo'];
    $result = \App\Support\DemoAccountService::seedAll($pdo);
}

echo "PRE-VERIFIED DEMO ACCOUNTS (FALLBACK CREDENTIALS):\n";
echo "--------------------------------------------------------------------------------\n";
foreach ($definitions as $key => $def) {
    echo sprintf(
        "[%s] %s\n  Email      : %s\n  Password   : %s\n  Role       : %s%s\n  Workspace  : /%s\n  Capabilities & Data:\n    %s\n\n",
        strtoupper($key),
        $def['name'],
        $def['email'],
        $def['password'],
        $def['role'],
        !empty($def['department']) ? ' (' . $def['department'] . ' + CAO/LEBDO All-Access Reviewer)' : '',
        $def['workspace'],
        $def['summary']
    );
}
echo "--------------------------------------------------------------------------------\n";

if (!$dryRun && isset($result)) {
    echo sprintf(
        "Seeded/Verified: 3 Accounts | %d Properties (IDs: %s) | 3 Wellness Proposals (Open/Improve/Partner) | Comparison #%d\n",
        count($result['property_ids'] ?? []),
        implode(', ', $result['property_ids'] ?? []),
        (int) ($result['wellness']['comparison_id'] ?? 0)
    );
}
echo "Done. You can also switch roles in 1 click using the top header 'Demo Role Switcher' bar.\n";
