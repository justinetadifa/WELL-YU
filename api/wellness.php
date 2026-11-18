<?php
declare(strict_types=1);
require __DIR__ . '/_bootstrap.php';
require_once dirname(__DIR__) . '/app/Repositories/WellnessRepository.php';
require_once dirname(__DIR__) . '/app/Support/WellnessBrief.php';

use App\Repositories\WellnessRepository;
use App\Support\{WellnessEngine, WellnessPolicy, WellnessBrief, PropertyCatalog};

api_handle(function (array $container): array {
    header('Cache-Control: private, no-store');
    $method = request_method(); $action = $_GET['action'] ?? 'bootstrap'; $actor = sfc_current_user();
    if (!in_array($method, ['GET', 'POST'], true)) { return [405, ['error' => 'Method not allowed.']]; }
    if ((int) ($_SERVER['CONTENT_LENGTH'] ?? 0) > 120000 && $action !== 'upload') { return [413, ['error' => 'Keep request data below 120 KB.']]; }
    $repository = new WellnessRepository($container['pdo'], fn (int $id, ?array $user): array => $container['properties']->find($id, $user));
    if ($method === 'GET' && $action === 'export_pilot_brief') {
        $pilot = WellnessEngine::siklabPilotPresets();
        $assessments = [];
        $scenarios = [];
        $idx = 1;
        foreach ($pilot['pathways'] as $mode => $pathway) {
            $pid = $idx++;
            $assessments[] = [
                'proposal_id' => $pid,
                'methodology_version' => WellnessEngine::VERSION,
                'snapshot' => [
                    'proposal' => [
                        'title' => $pathway['title'],
                        'mode' => $mode,
                        'review_status' => 'reviewed',
                        'review' => ['scope' => 'San Fernando City Wellness Benchmark'],
                        'payload' => [
                            'concept' => $pathway['subtitle'],
                            'intended_users' => $pathway['need_summary'],
                            'location' => 'Brgy. ' . $pathway['barangay'] . ', San Fernando City, La Union',
                            'source_need_id' => $pid,
                        ],
                    ],
                    'configuration' => $pilot['configuration'],
                ],
                'result' => $pathway['assessment'],
            ];
            $scenarios[(string) $pid] = [[
                'name' => strtoupper($mode) . ' Pathway Financial & Incentive Model',
                'is_baseline' => $mode === 'open',
                'input' => $pathway['scenario_input'],
                'result' => $pathway['scenario'],
            ]];
        }
        $comparisonPayload = [
            'name' => $pilot['pilot_title'] . ' — 3-Way Pathway Comparison (Open vs. Improve vs. Partner)',
            'created_at' => '2026-04-10T08:00:00Z',
            'snapshot' => [
                'methodology_version' => WellnessEngine::VERSION,
                'assessments' => $assessments,
                'scenarios' => $scenarios,
            ],
        ];
        $comparisonPayload['snapshot_sha256'] = WellnessEngine::hash($comparisonPayload['snapshot']);
        $pdf = WellnessBrief::pdf($comparisonPayload);
        header('Content-Type: application/pdf');
        header('Content-Disposition: attachment; filename="welyu-wellness-comparison-brief.pdf"');
        header('Content-Length: ' . strlen($pdf));
        echo $pdf;
        exit;
    }
    if ($method === 'POST' && in_array($action, ['preview_assessment', 'preview_scenario'], true)) {
        $input = read_json_input();
        if ($action === 'preview_scenario') { return ['result' => WellnessEngine::scenario($input, isset($input['baseline']) && is_array($input['baseline']) ? $input['baseline'] : null)]; }
        $isPilotPreset = !empty($input['siklab_pilot_preset']) || WellnessPolicy::reviewer($actor);
        $configuration = WellnessEngine::object($input['configuration'] ?? []);
        if (empty($input['siklab_pilot_preset'])) {
            $configuration['validation_status'] = 'provisional';
        }
        return ['result' => WellnessEngine::assess(WellnessRepository::scopeEvidence($input, $isPilotPreset), WellnessEngine::configuration($configuration))];
    }
    $readiness = $repository->readiness();
    if ($method === 'GET' && $action === 'bootstrap') {
        $barangays = PropertyCatalog::barangays(); $ready = $readiness['ready'];
        $properties = array_map(static fn (array $p): array => array_intersect_key($p, array_flip(['id', 'name', 'barangay', 'lat', 'lng', 'area', 'category', 'subcategory', 'price', 'leasePrice', 'listingPurpose'])), $container['properties']->all($actor));
        return ['workspace' => $readiness, 'signed_in' => $actor !== null, 'reviewer' => WellnessPolicy::reviewer($actor), 'corrections' => $ready ? $repository->corrections($actor) : [], 'criteria' => WellnessEngine::CRITERIA, 'criteria_metadata' => WellnessEngine::CRITERIA_METADATA, 'siklab_pilot' => WellnessEngine::siklabPilotPresets(), 'barangay_profiles' => WellnessEngine::barangayProfiles(), 'methodology_version' => WellnessEngine::VERSION, 'categories' => WellnessEngine::CATEGORIES, 'barriers' => WellnessEngine::BARRIERS, 'readiness_keys' => WellnessEngine::READINESS, 'barangays' => WellnessEngine::isList($barangays) ? $barangays : array_keys($barangays), 'properties' => $properties,
            'services' => $ready ? $repository->list('service', $actor) : [], 'needs' => $ready ? $repository->list('need', $actor) : [], 'proposals' => $ready && $actor ? $repository->list('proposal', $actor) : [], 'pipeline' => $ready ? $repository->pipeline($actor) : [], 'configurations' => $ready ? $repository->configurations() : [], 'assessments' => $ready && $actor ? $repository->assessments($actor) : [], 'comparisons' => $ready && $actor ? $repository->comparisons($actor) : []];
    }
    if (!$readiness['ready']) { return [503, ['error' => 'The wellness workspace is awaiting setup. You can explore the assessment and simulator without saving.', 'setup_pending' => true]]; }
    if ($action === 'radar' && $method === 'POST') {
        $input = read_json_input(); $services = array_values(array_filter($repository->list('service', $actor), static fn (array $row): bool => $row['review_status'] === 'reviewed'));
        return WellnessEngine::radar($input, $services);
    }
    if ($actor === null) { return [401, ['error' => 'Sign in to save records, access private evidence or export a brief.']]; }
    if ($method === 'GET') {
        $id = (int) ($_GET['id'] ?? 0); $kind = (string) ($_GET['kind'] ?? 'proposal');
        if ($action === 'record') { return ['record' => $repository->record($kind, $id, $actor)]; }
        if ($action === 'assessment') { return ['assessment' => $repository->assessment($id, $actor)]; }
        if ($action === 'scenarios') { return ['scenarios' => $repository->scenarios($id, $actor)]; }
        if ($action === 'timeline') { return ['events' => $repository->events($kind, $id, $actor), 'documents' => $repository->documents($kind, $id, $actor)]; }
        if ($action === 'comparison') { return ['comparison' => $repository->comparison($id, $actor)]; }
        if ($action === 'export') {
            $comparison = $repository->comparison($id, $actor); $pdf = WellnessBrief::pdf($comparison);
            header('Content-Type: application/pdf'); header('Content-Disposition: attachment; filename="welyu-investment-brief-' . $id . '.pdf"'); header('Content-Length: ' . strlen($pdf)); echo $pdf; exit;
        }
        if ($action === 'document') {
            $document = $repository->document($id, $actor);
            if (!preg_match('/^[a-f0-9]{32}\.(pdf|jpg|png)$/D', $document['storage_name'])) { throw new OutOfBoundsException('Document is unavailable.'); }
            $path = dirname(__DIR__) . '/data/wellness-documents/' . $document['storage_name'];
            if (!is_file($path)) { throw new OutOfBoundsException('Document is unavailable.'); }
            header('X-Content-Type-Options: nosniff'); header('Content-Type: ' . $document['mime_type']); header('Content-Disposition: attachment; filename="evidence-' . $id . '.' . pathinfo($path, PATHINFO_EXTENSION) . '"'); readfile($path); exit;
        }
    } else {
        if ($action === 'upload') {
            $kind = (string) ($_POST['kind'] ?? ''); $id = (int) ($_POST['id'] ?? 0); $repository->record($kind, $id, $actor, true);
            $file = $_FILES['document'] ?? [];
            if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK || !is_uploaded_file($file['tmp_name'] ?? '') || (int) ($file['size'] ?? 0) < 1 || (int) $file['size'] > 10 * 1024 * 1024) { throw new InvalidArgumentException('Choose a PDF, JPG or PNG of at most 10 MB.'); }
            $mime = (new finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']); $extension = ['application/pdf' => 'pdf', 'image/jpeg' => 'jpg', 'image/png' => 'png'][$mime] ?? null;
            if ($extension === null || ($extension === 'pdf' && file_get_contents($file['tmp_name'], false, null, 0, 5) !== '%PDF-') || ($extension !== 'pdf' && !getimagesize($file['tmp_name']))) { throw new InvalidArgumentException('Invalid evidence file.'); }
            $directory = dirname(__DIR__) . '/data/wellness-documents';
            if (!is_dir($directory) && !mkdir($directory, 0700, true) && !is_dir($directory)) { throw new RuntimeException('Private evidence storage is unavailable.'); }
            $name = bin2hex(random_bytes(16)) . '.' . $extension; $target = $directory . '/' . $name;
            if (!move_uploaded_file($file['tmp_name'], $target)) { throw new RuntimeException('Unable to store evidence.'); }
            try { $documentId = $repository->addDocument($kind, $id, ['original_name' => WellnessEngine::text(basename($file['name']), 190), 'storage_name' => $name, 'mime_type' => $mime, 'size_bytes' => (int) $file['size'], 'sha256' => hash_file('sha256', $target)], $actor); }
            catch (Throwable $error) { unlink($target); throw $error; }
            return ['id' => $documentId];
        }
        $input = read_json_input();
        return match ($action) {
            'save_service' => ['record' => $repository->saveDirectory('service', $input, $actor)],
            'save_need' => ['record' => $repository->saveDirectory('need', $input, $actor)],
            'save_proposal' => ['proposal' => $repository->createProposal($input, $actor)],
            'save_configuration' => ['configuration' => $repository->createConfiguration($input, $actor)],
            'save_assessment' => ['assessment' => $repository->assess($input, $actor)],
            'save_scenario' => ['scenario' => $repository->saveScenario($input, $actor)],
            'save_comparison' => ['comparison' => $repository->compare($input, $actor)],
            'review' => ['record' => $repository->review($input, $actor)],
            'follow_up' => ['event' => $repository->addFollowUp($input, $actor)],
            'import_preview' => $repository->importPreview((string) ($input['kind'] ?? ''), WellnessEngine::object($input['records'] ?? []), $actor),
            'import_commit' => $repository->importCommit((string) ($input['kind'] ?? ''), WellnessEngine::object($input['records'] ?? []), (string) ($input['input_sha256'] ?? ''), $actor),
            default => [405, ['error' => 'Unsupported wellness action.']],
        };
    }
    return [405, ['error' => 'Unsupported wellness action.']];
});
