<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once dirname(__DIR__, 2) . '/app/Support/WellnessEngine.php';
require_once dirname(__DIR__, 2) . '/app/Support/WellnessBrief.php';
use App\Support\{WellnessEngine, WellnessBrief};
$input = json_decode(stream_get_contents(STDIN), true, 64, JSON_THROW_ON_ERROR);
if ($input['action'] === 'configuration') { echo json_encode(WellnessEngine::configuration($input['input']), JSON_THROW_ON_ERROR); exit; }
if ($input['action'] === 'pdf') { echo WellnessBrief::pdf($input['comparison']); exit; }
$result = $input['action'] === 'scenario'
    ? WellnessEngine::scenario($input['input'], $input['baseline'] ?? null)
    : WellnessEngine::assess($input['input'], WellnessEngine::configuration($input['configuration']));
echo json_encode($result, JSON_THROW_ON_ERROR);
