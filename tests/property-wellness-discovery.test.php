<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once dirname(__DIR__) . '/app/Support/PropertyWellnessDiscovery.php';
use App\Support\PropertyWellnessDiscovery as Discovery;
$checks = 0;
$check = static function (bool $ok, string $message) use (&$checks): void {
    if (!$ok) { throw new RuntimeException($message); }
    $checks++;
};
$feature = static fn (string $id, string $category, float $lat, float $lng): array => ['id' => $id, 'geometry' => ['type' => 'Point', 'coordinates' => [$lng, $lat]], 'properties' => ['name' => 'Synthetic ' . $id, 'category' => $category, 'status' => 'operating', 'barangay' => 'Synthetic location', 'source' => 'Synthetic inventory']];
$inventory = ['generated' => '2026-04-12', 'features' => [$feature('a', 'hospital', 0, 0), $feature('b', 'pharmacy', 0, .002), $feature('c', 'park', 0, .003)]];
$listing = static fn (int $id, mixed $lat, mixed $lng): array => ['id' => $id, 'name' => 'Synthetic property ' . $id, 'lat' => $lat, 'lng' => $lng, 'status' => 'Available', 'approvalState' => 'approved', 'listingPurpose' => 'lease', 'leasePrice' => 18000, 'leasePriceUnit' => 'sqm', 'leasePeriod' => 'year'];
$properties = [$listing(3, 0, .009), $listing(1, 0, .001), $listing(2, null, null), $listing(4, 0, .2), $listing(5, 0, .001) + ['isDeleted' => true], $listing(6, 0, .001) + ['isArchived' => true]];
$pending = $listing(7, 0, .001); $pending['approvalState'] = 'pending_review'; $properties[] = $pending;
$sold = $listing(8, 0, .001); $sold['status'] = 'Sold'; $properties[] = $sold;
$before = serialize([$properties, $inventory]);
$result = Discovery::snapshot($properties, $inventory);
$check(serialize([$properties, $inventory]) === $before, 'Discovery does not mutate its inputs.');
$check($result['eligible_property_count'] === 4 && $result['missing_coordinates_count'] === 1, 'Eligibility excludes deleted, archived, pending and sold records; unknown coordinates stay unknown.');
$check($result['candidate_count'] === 2 && array_column($result['opportunities'], 'property_id') === [1, 3], 'Nearest health anchor ranks mapped available properties; distant listings are excluded.');
$check($result['opportunities'][0]['nearest_distance_m'] === 111, 'Independent equatorial distance: .001 longitude degrees is about 111 meters.');
$check($result['opportunities'][0]['nearby_count'] === 3 && $result['radius_m'] === 2000, 'Nearby count includes all recorded wellness categories in the explicit 2 km radius.');
$check($result['opportunities'][0]['leasePrice'] === 18000 && $result['opportunities'][0]['leasePriceUnit'] === 'sqm' && $result['opportunities'][0]['leasePeriod'] === 'year', 'Pricing preserves total versus per-area units and period.');
$check($result['opportunities'][0]['idea_id'] === 'rehabilitation', 'Hospital adjacency proposes a complementary business concept.');
$check($result['facilities'][0]['evidence_label'] === 'Local inventory record' && $result['facilities'][0]['source_checked_on'] === null, 'Unverified baseline entries never inherit official-source verification.');
$check($result['inventory_date'] === '2026-04-12' && $result['sources_checked_on'] === '2026-11-18', 'Inventory collection and official source review have separate dates.');
$check(Discovery::snapshot([$listing(1, true, 120)], $inventory)['missing_coordinates_count'] === 1, 'Boolean coordinates are not silently cast to numbers.');
$check(Discovery::snapshot([$listing(1, 91, 120)], $inventory)['missing_coordinates_count'] === 1, 'Out-of-range coordinates are excluded.');
$check(Discovery::snapshot([], $inventory)['opportunities'] === [] && count(Discovery::snapshot([], $inventory)['ideas']) === 6, 'No listings still returns the facility directory and business concepts.');
$check(Discovery::snapshot([$listing(1, 0, .001)], ['features' => []])['opportunities'] === [], 'Missing inventory does not invent nearby facilities.');
$ties = Discovery::snapshot([$listing(9, 0, .001), $listing(4, 0, .001), $listing(4, 0, .001)], $inventory);
$check(array_column($ties['opportunities'], 'property_id') === [4, 9] && $ties['eligible_property_count'] === 2, 'Ties have stable ID ordering and duplicate property IDs do not inflate results.');
$lumedInventory = ['features' => [$feature('hlth-lumed', 'hospital', 0, 0)]];
$lumed = Discovery::snapshot([$listing(1, 0, 0)], $lumedInventory);
$check($lumed['facilities'][0]['barangay'] === 'Catbangen' && $lumed['facilities'][0]['lat'] === null && $lumed['opportunities'] === [], 'LUMED official address correction withholds contradictory inventory coordinates from ranking.');
$real = Discovery::snapshot([]);
$check($real['facility_count'] === 32 && $real['official_source_count'] === 5, 'The existing 32-entry inventory loads, with five explicit official identity/address references.');
echo "Property wellness discovery: {$checks} checks passed.\n";
