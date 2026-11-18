<?php
declare(strict_types=1);

/**
 * Explicit, opt-in CLI seeder for the La Union SIKLAB Conference & Sprint 2026 showcase.
 *
 * SAFETY GUARANTEES:
 * - Never executes via HTTP (CLI only).
 * - Requires `app/config.php` to keep `auto_migrate = false` and `auto_seed = false`.
 * - Defaults to `--dry-run` unless `--apply` is explicitly passed on the command line.
 */
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

$root = dirname(__DIR__);
require_once $root . '/app/Support/WellnessEngine.php';

$args = array_slice($argv, 1);
$apply = in_array('--apply', $args, true);
$dryRun = in_array('--dry-run', $args, true) || !$apply;

if (array_diff($args, ['--apply', '--dry-run']) !== []) {
    fwrite(STDERR, "Usage: php scripts/seed-siklab-showcase.php [--dry-run | --apply]\n");
    exit(1);
}

$config = require $root . '/app/config.php';
if (($config['db']['name'] ?? '') !== 'well_yu_db'
    || !empty($config['app']['auto_migrate'])
    || !empty($config['app']['auto_seed'])
    || !empty($config['db']['auto_create'])) {
    fwrite(STDERR, "Refusing to run: well_yu_db isolation or disabled-automation safety check failed.\n");
    exit(1);
}

$presets = \App\Support\WellnessEngine::siklabPilotPresets();
$barangays = \App\Support\WellnessEngine::barangayProfiles();

echo "=== wELYU · SIKLAB 2026 San Fernando Pilot Showcase Seeder ===\n";
echo "Target Database : " . $config['db']['name'] . "\n";
echo "Mode            : " . ($apply ? "APPLY (explicit CLI opt-in)" : "DRY-RUN (read-only preview; pass --apply to write)") . "\n";
echo "Pathways        : " . count($presets['pathways'] ?? []) . " (Open, Improve, Partner)\n";
echo "Barangay Pilots : " . implode(', ', array_keys($barangays)) . "\n";

if ($dryRun) {
    foreach ($presets['pathways'] as $mode => $pathway) {
        $assessment = $pathway['assessment'] ?? \App\Support\WellnessEngine::assess($pathway['evidence'], $presets['configuration']);
        $scenario = $pathway['scenario'] ?? \App\Support\WellnessEngine::scenario($pathway['scenario_input']);
        echo sprintf(
            "  - [%s] %s (%s): IAI %.2f/100 | Break-even %d visits/mo | Payback %.1f mos\n",
            strtoupper($mode),
            $pathway['title'] ?? $mode,
            $pathway['barangay'] ?? 'San Fernando',
            $assessment['iai_score'] ?? 0.0,
            $scenario['break_even_visits_per_month'] ?? 0,
            $scenario['viability']['estimated_payback_months'] ?? 0.0
        );
    }
    echo "Dry-run complete. No database connection opened or modified.\n";
    exit(0);
}

$dsn = sprintf(
    'mysql:host=%s;port=%d;dbname=well_yu_db;charset=utf8mb4',
    $config['db']['host'],
    (int) $config['db']['port']
);
$pdo = new PDO($dsn, $config['db']['user'], $config['db']['pass'], [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES => false,
]);

$pdo->beginTransaction();
try {
    $ownerId = (int) ($pdo->query('SELECT id FROM users ORDER BY id ASC LIMIT 1')->fetchColumn() ?: 0);
    if ($ownerId < 1) {
        throw new RuntimeException('At least one user record must exist in well_yu_db.users before seeding welyu_needs / welyu_services.');
    }

    $needStmt = $pdo->prepare(
        'INSERT IGNORE INTO welyu_needs (owner_user_id, title, category, barangay, lat, lng, payload_json, fingerprint, review_status, review_json, reviewer_user_id, created_at)
         VALUES (:owner, :title, :category, :barangay, :lat, :lng, :payload, :fp, :status, :review, :reviewer, NOW())'
    );
    $insertedNeeds = 0;
    foreach ($presets['pathways'] as $mode => $pathway) {
        $title = $pathway['title'];
        $brgy = $pathway['barangay'];
        $fp = hash('sha256', 'siklab-2026-need:' . $mode . ':' . $brgy);
        $payload = [
            'title' => $title,
            'category' => $pathway['category'],
            'barangay' => $brgy,
            'lat' => $pathway['lat'],
            'lng' => $pathway['lng'],
            'summary' => $pathway['need_summary'],
            'barriers' => $pathway['barriers'],
            'verified_on' => '2026-04-10',
        ];
        $needStmt->execute([
            ':owner' => $ownerId,
            ':title' => $title,
            ':category' => $pathway['category'],
            ':barangay' => $brgy,
            ':lat' => $pathway['lat'],
            ':lng' => $pathway['lng'],
            ':payload' => json_encode($payload, JSON_THROW_ON_ERROR),
            ':fp' => $fp,
            ':status' => 'reviewed',
            ':review' => json_encode(['scope' => 'SIKLAB 2026 Barangay Pilot Registry', 'reviewed_on' => '2026-04-10'], JSON_THROW_ON_ERROR),
            ':reviewer' => $ownerId,
        ]);
        $insertedNeeds += $needStmt->rowCount();
    }

    $srvStmt = $pdo->prepare(
        'INSERT IGNORE INTO welyu_services (owner_user_id, title, category, barangay, lat, lng, payload_json, fingerprint, review_status, review_json, reviewer_user_id, created_at)
         VALUES (:owner, :title, :category, :barangay, :lat, :lng, :payload, :fp, :status, :review, :reviewer, NOW())'
    );
    $baselineServices = [
        ['Biday Daytime Therapy & Rehab Clinic', 'rehab_mobility', 'Biday', 16.6258, 120.3234, 'Mon-Fri 8:00 AM - 5:00 PM', 'PHP 350 - 500'],
        ['Catbangen Community Physical Therapy Center', 'preventive_care', 'Catbangen', 16.6142, 120.3118, 'Mon-Sat 8:00 AM - 5:00 PM', 'PHP 300 - 450'],
        ['Poro Point Active Fitness & Coastal Studio', 'fitness_recovery', 'Poro', 16.6095, 120.2965, 'Mon-Sat 6:00 AM - 7:00 PM', 'PHP 250 - 400'],
    ];
    $insertedServices = 0;
    foreach ($baselineServices as [$name, $cat, $brgy, $lat, $lng, $hours, $price]) {
        $fp = hash('sha256', 'siklab-2026-service:' . $name . ':' . $brgy);
        $payload = [
            'title' => $name,
            'category' => $cat,
            'barangay' => $brgy,
            'lat' => $lat,
            'lng' => $lng,
            'hours' => $hours,
            'price_band' => $price,
            'operator_kind' => 'private',
            'verified_on' => '2026-04-10',
        ];
        $srvStmt->execute([
            ':owner' => $ownerId,
            ':title' => $name,
            ':category' => $cat,
            ':barangay' => $brgy,
            ':lat' => $lat,
            ':lng' => $lng,
            ':payload' => json_encode($payload, JSON_THROW_ON_ERROR),
            ':fp' => $fp,
            ':status' => 'reviewed',
            ':review' => json_encode(['scope' => 'City Health & BPLO Registry 2026', 'reviewed_on' => '2026-04-10'], JSON_THROW_ON_ERROR),
            ':reviewer' => $ownerId,
        ]);
        $insertedServices += $srvStmt->rowCount();
    }

    $pdo->commit();
    echo sprintf("Seeded %d welyu_needs and %d welyu_services into well_yu_db.\n", $insertedNeeds, $insertedServices);
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    fwrite(STDERR, "Seed failed and rolled back: " . $e->getMessage() . "\n");
    exit(1);
}
