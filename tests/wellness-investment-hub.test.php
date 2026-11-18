<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once dirname(__DIR__) . '/app/Support/WellnessInvestmentHub.php';
use App\Support\WellnessInvestmentHub as Hub;

$checks = 0;
$check = static function (bool $condition, string $message) use (&$checks): void {
    if (!$condition) { throw new RuntimeException($message); }
    $checks++;
};
$inventory = ['generated' => '2026-04-12', 'features' => [
    ['id' => 'synthetic-hospital', 'geometry' => ['type' => 'Point', 'coordinates' => [0, 0]], 'properties' => ['name' => 'Synthetic care anchor', 'category' => 'hospital', 'status' => 'operating', 'address' => 'Local baseline address']],
    ['id' => 'synthetic-rehab', 'geometry' => ['type' => 'Point', 'coordinates' => [.002, 0]], 'properties' => ['name' => 'Synthetic rehab', 'category' => 'rehab', 'status' => 'operating']],
]];
$sources = ['checked_on' => '2026-11-18', 'featured' => [
    ['id' => 'synthetic-hospital', 'name' => 'Synthetic public facility', 'category' => 'hospital', 'address' => 'Official synthetic address', 'sector' => 'public', 'services' => ['Care', 'Care', null, 'Rehabilitation'], 'source_url' => 'https://facility.example/official', 'contact_url' => 'javascript:alert(1)', 'source_title' => 'Synthetic official source', 'private_notes' => 'Never public', 'offer_status' => 'Accepting funds'],
    ['id' => 'synthetic-private', 'name' => 'Synthetic private provider', 'category' => 'hospital', 'sector' => 'private', 'source_url' => 'https://provider.example/contact'],
    ['id' => 'synthetic-private', 'name' => 'Duplicate provider', 'sector' => 'private', 'source_url' => 'https://provider.example/contact'],
    ['id' => 'bad', 'name' => 'Unlinked record', 'source_url' => 'javascript:alert(1)'],
], 'idea_sources' => [['idea_id' => 'rehabilitation', 'source_url' => 'https://facility.example/services', 'source_title' => 'Synthetic rehabilitation source', 'evidence_summary' => 'Synthetic service context only.']]];
$listing = ['id' => 1, 'name' => 'Synthetic listed site', 'approvalState' => 'approved', 'status' => 'Available', 'lat' => 0, 'lng' => .001, 'price' => 1000, 'ownerEmail' => 'must-not-appear@example.test', 'reviewNote' => 'Private notes'];
$pending = array_replace($listing, ['id' => 2, 'approvalState' => 'pending_review']);
$deleted = array_replace($listing, ['id' => 3, 'isDeleted' => true]);
$before = serialize([$sources, $inventory, $listing]);
$result = Hub::snapshot([$listing, $pending, $deleted], $sources, $inventory);
$featuredById = array_column($result['featured_facilities'], null, 'id');
$facilitiesById = array_column($result['facilities'], null, 'id');
$check(serialize([$sources, $inventory, $listing]) === $before, 'Hub never mutates supplied records.');
$check($result['featured_count'] === 2 && count($result['featured_facilities']) === 2, 'Featured records require a safe source link and deduplicate by ID.');
$check($featuredById['synthetic-hospital']['services'] === ['Care', 'Rehabilitation'], 'Service list drops malformed and duplicate entries.');
$check($featuredById['synthetic-hospital']['contact_url'] === 'https://facility.example/official', 'Unsafe contact URLs fall back to the official source.');
$check($featuredById['synthetic-hospital']['action_label'] === 'Explore complementary services' && $featuredById['synthetic-private']['action_label'] === 'Explore partnership', 'Public and private operators have distinct exploration actions.');
$check($featuredById['synthetic-hospital']['offer_status'] === 'No investment offer verified', 'Source input cannot accidentally imply an investment offer.');
$check(array_column($result['featured_facilities'], 'id') === ['synthetic-private', 'synthetic-hospital'], 'Featured facilities use alphabetical name order rather than an investment rank.');
$check(!str_contains(json_encode($result), 'Never public') && !str_contains(json_encode($result), 'must-not-appear') && !str_contains(json_encode($result), 'Private notes'), 'Only normalized public fields leave the hub.');
$check(array_column($result['opportunities'], 'property_id') === [1], 'Pending and deleted listings do not enter the public shortlist.');
$check($result['opportunities'][0]['nearest_distance_m'] === 111, 'Public shortlist retains independently checkable straight-line distance.');
$check($facilitiesById['synthetic-hospital']['address'] === 'Official synthetic address', 'Official source address is used for directory display.');
$rehab = array_values(array_filter($result['ideas'], static fn (array $idea): bool => $idea['id'] === 'rehabilitation'))[0];
$check($rehab['evidence_count'] === 2 && count($rehab['evidence_categories']) === 2, 'Concept evidence counts only related inventory categories.');
$check($rehab['source_url'] === 'https://facility.example/services' && count($rehab['next_steps']) === 3, 'Concept retains source and practical validation steps.');
$check(str_contains($rehab['evidence_note'], 'not evidence of an unmet') && $rehab['demand_status'] === 'Requires local demand validation', 'Supply counts do not claim a service gap.');
$check(array_column($result['investment_modes'], 'id') === ['open', 'improve', 'partner'], 'Investment approaches distinguish opening, improving and partnering.');
$check($result['source_metadata']['checked_on'] === '2026-11-18' && str_contains($result['source_metadata']['investment_notice'], 'No equity offer'), 'Reviewed date and investment limits are explicit.');
$empty = Hub::snapshot([], [], ['features' => []]);
$check($empty['opportunities'] === [] && $empty['featured_facilities'] === [] && $empty['facilities'] === [] && count($empty['ideas']) === 6, 'Missing data returns honest empty arrays while keeping concepts available.');
$check($empty['ideas'][0]['evidence_count'] === 0 && str_contains($empty['ideas'][0]['evidence_basis'], 'No matching care anchor'), 'Missing inventory does not create supply evidence.');
$conflict = $sources; $conflict['featured'][0]['exclude_inventory_coordinates'] = true;
$withoutPoint = Hub::snapshot([$listing], $conflict, ['features' => [$inventory['features'][0]]]);
$check($withoutPoint['opportunities'] === [], 'An explicitly flagged address conflict withholds its inventory point from proximity.');
$badDate = Hub::snapshot([], ['checked_on' => '2026-02-30'], []);
$check($badDate['source_metadata']['checked_on'] === null, 'Invalid review dates are not displayed as verified dates.');
echo "Wellness investment hub: {$checks} checks passed.\n";
