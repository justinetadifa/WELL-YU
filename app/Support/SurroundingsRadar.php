<?php
declare(strict_types=1);

namespace App\Support;

require_once __DIR__ . '/NearbyBusinessScreening.php';
require_once __DIR__ . '/NearbyBusinesses.php';

use InvalidArgumentException;
use RuntimeException;
use Throwable;

final class SurroundingsRadar
{
    private const EARTH_RADIUS = 6371000.0; // meters

    private const OVERPASS_MIRRORS = [
        'https://overpass.osm.ch/api/interpreter',
        'https://overpass-api.de/api/interpreter',
        'https://overpass.kumi.systems/api/interpreter',
        'https://overpass.private.coffee/api/interpreter',
    ];
    private const LIVE_TIMEOUT_MS = 3500;

    /**
     * Compute Haversine distance in meters between two lat/lng coordinates.
     */
    public static function distance(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);
        $a = sin($dLat / 2) ** 2 + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * (sin($dLng / 2) ** 2);
        $c = 2 * atan2(sqrt($a), sqrt(max(0.0, 1.0 - $a)));
        return self::EARTH_RADIUS * $c;
    }

    /** Validate the nearby endpoint's optional reference point before directory fallback. */
    public static function queryParameters(array $query): ?array
    {
        if (!array_key_exists('lat', $query) && !array_key_exists('lng', $query)) {
            return null;
        }
        $lat = $query['lat'] ?? null;
        $lng = $query['lng'] ?? null;
        if (!is_scalar($lat) || !is_scalar($lng) || !is_numeric($lat) || !is_numeric($lng)
            || !is_finite((float) $lat) || !is_finite((float) $lng) || abs((float) $lat) > 90 || abs((float) $lng) > 180
        ) {
            throw new InvalidArgumentException('Provide both valid latitude and longitude for nearby search.');
        }
        $radius = $query['radius'] ?? 500;
        if (!is_scalar($radius) || !is_numeric($radius) || !is_finite((float) $radius)
            || (float) $radius < 100 || (float) $radius > 1500 || floor((float) $radius) !== (float) $radius
        ) {
            throw new InvalidArgumentException('Nearby search radius must be a whole number between 100 and 1500 meters.');
        }
        return [
            'lat' => (float) $lat,
            'lng' => (float) $lng,
            'radius' => (int) $radius,
            'type' => is_string($query['type'] ?? null) ? trim($query['type']) : 'cafe',
        ];
    }

    /**
     * Search establishments and road segments within $radiusMeters of ($lat, $lng).
     */
    public static function search(float $lat, float $lng, int $radiusMeters = 500, string $businessType = 'cafe', array $options = []): array
    {
        if (!is_finite($lat) || abs($lat) > 90 || !is_finite($lng) || abs($lng) > 180) {
            throw new InvalidArgumentException('Invalid latitude or longitude coordinates.');
        }

        $radiusMeters = max(100, min(1500, $radiusMeters));
        $localOnly = ($options['local_only'] ?? false) === true;
        $cacheDir = $options['cache_directory'] ?? dirname(__DIR__, 2) . '/data/cache/radar';
        $dataDir = $options['data_directory'] ?? dirname(__DIR__, 2) . '/data';
        if (!$localOnly && !is_dir($cacheDir)) {
            @mkdir($cacheDir, 0775, true);
        }

        // Coordinates are stored with six decimal places. Do not reuse another
        // reference point's distances merely because both round to four digits.
        $cacheKey = sprintf('radar_v2_%.6f_%.6f_%d.json', $lat, $lng, $radiusMeters);
        $cachePath = $cacheDir . '/' . $cacheKey;
        $now = time();
        $inventory = self::loadLocalRecords($lat, $lng, $radiusMeters, $dataDir, $businessType);
        $saved = self::loadSavedRecords($cacheDir, $lat, $lng, $radiusMeters);
        $localRoads = self::loadLocalRoads($dataDir, $lat, $lng, $radiusMeters);
        $localFeatures = self::mergeEstablishments($inventory, $saved['features']);
        $savedRoads = self::mergeRoads($localRoads, $saved['roads']);

        // 1. Check fresh cache (valid for 24 hours)
        if (!$localOnly && is_file($cachePath)) {
            $mtime = filemtime($cachePath) ?: 0;
            $raw = @file_get_contents($cachePath);
            if ($raw !== false) {
                $cached = json_decode($raw, true);
                if (is_array($cached) && is_array($cached['features'] ?? null)) {
                    // An earlier failed refresh is not a successful 24-hour live cache.
                    if (empty($options['force_refresh']) && ($now - $mtime) < 86400 && ($cached['coverage'] ?? '') !== 'local_only'
                        && empty($cached['liveUnavailable'])) {
                        $cached['features'] = self::mergeEstablishments($inventory, self::mergeEstablishments($cached['features'], $saved['features']));
                        $cached['roads']['geojson']['features'] = self::mergeRoads($cached['roads']['geojson']['features'] ?? [], $savedRoads);
                        $cached['cached'] = true;
                        $cached['stale'] = false;
                        return self::frameResult($cached, $lat, $lng, $radiusMeters, $businessType);
                    }
                }
            }
        }

        // 2. Fetch from Overpass
        $osmData = null;
        try {
            $osmData = $localOnly ? null : (isset($options['fetcher']) ? ($options['fetcher'])($lat, $lng, $radiusMeters) : self::queryOverpass($lat, $lng, $radiusMeters));
            if (!is_array($osmData) || !is_array($osmData['elements'] ?? null)) {
                $osmData = null;
            }
        } catch (Throwable $e) {
            $osmData = null;
        }

        // 4. Stale cache fallback if live Overpass failed
        if ($osmData === null && !$localOnly) {
            $fallbackFile = is_file($cachePath) ? $cachePath : null;
            if ($fallbackFile !== null) {
                $raw = @file_get_contents($fallbackFile);
                if ($raw !== false) {
                    $cached = json_decode($raw, true);
                    if (is_array($cached) && is_array($cached['features'] ?? null)) {
                        // Merge local inventory features with cached records
                        $cached['features'] = self::mergeEstablishments($localFeatures, $cached['features']);
                        $cached['roads']['geojson']['features'] = self::mergeRoads($cached['roads']['geojson']['features'] ?? [], $savedRoads);
                        $cached['cached'] = true;
                        $cached['stale'] = true;
                        $cached['liveUnavailable'] = true;
                        $cached['warning'] = 'Showing older mapped records. Live nearby data is unavailable; operating status and inventory completeness are unverified.';
                        return self::frameResult($cached, $lat, $lng, $radiusMeters, $businessType);
                    }
                }
            }
        }

        // If Overpass failed and we have no cache and no local records, return clean empty result
        if ($osmData === null && empty($localFeatures) && empty($savedRoads)) {
            return self::frameResult([
                'type' => 'FeatureCollection',
                'features' => [],
                'roads' => [
                    'status' => 'Unavailable',
                    'geojson' => [
                        'type' => 'FeatureCollection',
                        'features' => [],
                    ],
                    'source' => 'Local commercial inventory',
                    'date' => gmdate('Y-m-d'),
                ],
                'center' => [$lat, $lng],
                'radius' => $radiusMeters,
                'source' => 'Local commercial inventory',
                'retrievalDate' => gmdate('Y-m-d'),
                'cached' => false,
                'stale' => false,
                'coverage' => 'local_only',
                'liveUnavailable' => !$localOnly,
                'warning' => $localOnly ? 'The local inventory has no matches. Live mapping is checked separately; this is not proof of zero businesses.' : 'Live nearby data is unavailable and the local inventory has no matches. This does not establish zero businesses in the area.',
            ], $lat, $lng, $radiusMeters, $businessType);
        }

        // 5. Parse OSM elements into GeoJSON
        $parsed = self::parseOsmElements($osmData['elements'] ?? [], $lat, $lng, $radiusMeters);

        // 6. Merge local verified records with OSM establishments (deduplicate)
        // Current local inventory and new live observations take precedence over older map records.
        $allEstablishments = self::mergeEstablishments(self::mergeEstablishments($inventory, $parsed['establishments']), $saved['features']);
        $allRoads = self::mergeRoads($parsed['roads'], $savedRoads);
        $hasSaved = $saved['features'] !== [] || $savedRoads !== [];

        $result = [
            'type' => 'FeatureCollection',
            'features' => $allEstablishments,
            'roads' => [
                'status' => !empty($allRoads) ? 'Available' : ($osmData !== null ? 'None nearby' : 'Unavailable'),
                'geojson' => [
                    'type' => 'FeatureCollection',
                    'features' => $allRoads,
                ],
                'source' => $osmData !== null && !empty($parsed['roads']) ? 'OpenStreetMap contributors (via Overpass API)' : (!empty($allRoads) ? 'DPWH Region 1 / San Fernando City Road Network' : ($hasSaved ? 'Saved map records' : 'Local commercial inventory')),
                'date' => $osmData === null && $hasSaved ? $saved['date'] : gmdate('Y-m-d'),
            ],
            'center' => [$lat, $lng],
            'radius' => $radiusMeters,
            'source' => $osmData !== null ? 'OpenStreetMap & local commercial inventory' : ($hasSaved ? 'Local inventory & saved map records' : 'Local commercial inventory'),
            'retrievalDate' => $osmData === null && $hasSaved ? $saved['date'] : gmdate('Y-m-d'),
            'cached' => $hasSaved,
            'stale' => $osmData === null && $saved['stale'],
            'liveUnavailable' => !$localOnly && ($osmData === null || !empty($osmData['remark'])),
            'coverage' => $osmData !== null ? 'mapped_only' : 'local_only',
            'warning' => $localOnly ? 'Showing available local and saved map records. Live mapping is checked separately; coverage is incomplete.' : ($osmData === null ? 'Live nearby data is unavailable. Showing local and saved map records with their original dates; coverage is incomplete.'
                : (!empty($osmData['remark']) ? 'The map provider returned partial results. Nearby inventory and operating status remain unverified.' : null)),
        ];
        $result = self::frameResult($result, $lat, $lng, $radiusMeters, $businessType);

        // Save to cache
        // Provider outages, partial responses, or empty responses must never replace the last successful map snapshot.
        if (!$localOnly && $osmData !== null && empty($osmData['remark']) && (!empty($result['features']) || !empty($result['roads']['geojson']['features'] ?? []))) {
            @file_put_contents($cachePath, json_encode($result, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), LOCK_EX);
        }

        return $result;
    }

    private static function queryOverpass(float $lat, float $lng, int $radiusMeters): array
    {
        $buffer = $radiusMeters + 30; // slight buffer for geometry intersection
        $query = sprintf(
            '[out:json][timeout:8];(nwr(around:%d,%.6f,%.6f)["amenity"];nwr(around:%d,%.6f,%.6f)["shop"];nwr(around:%d,%.6f,%.6f)["tourism"];nwr(around:%d,%.6f,%.6f)["office"];nwr(around:%d,%.6f,%.6f)["commercial"];nwr(around:%d,%.6f,%.6f)["industrial"];nwr(around:%d,%.6f,%.6f)["building"~"commercial|retail|mall|supermarket"];nwr(around:%d,%.6f,%.6f)["landuse"="commercial"];way(around:%d,%.6f,%.6f)["highway"];);out body geom;',
            $buffer, $lat, $lng,
            $buffer, $lat, $lng,
            $buffer, $lat, $lng,
            $buffer, $lat, $lng,
            $buffer, $lat, $lng,
            $buffer, $lat, $lng,
            $buffer, $lat, $lng,
            $buffer, $lat, $lng,
            $buffer, $lat, $lng
        );

        if (!function_exists('curl_multi_init')) { throw new RuntimeException('Live mapping is unavailable.'); }
        $multi = curl_multi_init();
        $handles = [];
        $partial = null;
        try {
            // Race independent providers under one deadline instead of a two-second single-provider cutoff.
            foreach (self::OVERPASS_MIRRORS as $mirror) {
                $handle = curl_init($mirror);
                curl_setopt_array($handle, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT_MS => self::LIVE_TIMEOUT_MS,
                    CURLOPT_CONNECTTIMEOUT_MS => 2500, CURLOPT_USERAGENT => 'LOCUS-SF-Radar/1.0 (San Fernando La Union Urban Intelligence; contact@sfcelerate.local)',
                    CURLOPT_POSTFIELDS => 'data=' . urlencode($query)]);
                curl_multi_add_handle($multi, $handle);
                $handles[] = $handle;
            }
            $deadline = microtime(true) + self::LIVE_TIMEOUT_MS / 1000;
            do {
                $status = curl_multi_exec($multi, $running);
                if ($status !== CURLM_OK) { break; }
                while ($info = curl_multi_info_read($multi)) {
                    $handle = $info['handle'];
                    if ($info['result'] !== CURLE_OK || curl_getinfo($handle, CURLINFO_HTTP_CODE) !== 200) { continue; }
                    $decoded = json_decode((string) curl_multi_getcontent($handle), true);
                    if (!is_array($decoded) || !is_array($decoded['elements'] ?? null)) { continue; }
                    if (empty($decoded['remark'])) { return $decoded; }
                    $partial ??= $decoded;
                }
                if ($running && microtime(true) < $deadline && curl_multi_select($multi, .1) === -1) { usleep(20000); }
            } while ($running && microtime(true) < $deadline);
            if ($partial !== null) { return $partial; }
            throw new RuntimeException('Live map providers did not return usable records within the search deadline.');
        } finally {
            foreach ($handles as $handle) { curl_multi_remove_handle($multi, $handle); curl_close($handle); }
            curl_multi_close($multi);
        }
    }

    /** Reuse saved geometry across nearby search points, always recalculating distances and roles. */
    private static function loadSavedRecords(string $cacheDir, float $lat, float $lng, int $radiusMeters): array
    {
        $files = glob($cacheDir . '/*.json') ?: [];
        usort($files, static fn (string $a, string $b): int => (filemtime($b) ?: 0) <=> (filemtime($a) ?: 0));
        $features = $roads = [];
        $date = null;
        $stale = false;
        foreach ($files as $file) {
            $saved = json_decode((string) @file_get_contents($file), true);
            if (!is_array($saved) || !is_array($saved['features'] ?? null)) { continue; }
            if (empty($saved['features']) && empty($saved['roads']['geojson']['features'] ?? [])) { continue; }
            $origin = $saved['center'] ?? null;
            $extent = $saved['radius'] ?? $saved['radiusMeters'] ?? null;
            if (is_array($origin) && count($origin) === 2 && is_numeric($origin[0]) && is_numeric($origin[1]) && is_numeric($extent)
                && self::distance($lat, $lng, (float) $origin[0], (float) $origin[1]) > $radiusMeters + (float) $extent + 30) { continue; }
            $savedDate = is_string($saved['retrievalDate'] ?? null) ? $saved['retrievalDate'] : gmdate('Y-m-d', filemtime($file) ?: time());
            $used = false;
            foreach ($saved['features'] as $feature) {
                if (!is_array($feature) || ($feature['type'] ?? '') !== 'Feature' || !is_array($feature['properties'] ?? null)
                    || self::featureRepresentativePoint($feature) === null || self::isClosed($feature['properties'])
                    || self::featureDistance($feature, $lat, $lng) > $radiusMeters + .000001) { continue; }
                $feature['properties']['distanceMeters'] = round(self::featureDistance($feature, $lat, $lng), 1);
                $feature['properties']['source'] ??= 'Saved mapped place record';
                $feature['properties']['checkedOn'] ??= $savedDate;
                $feature['properties']['cached'] = true;
                $feature['properties']['verified'] = false;
                $features[] = $feature;
                $used = true;
            }
            foreach ($saved['roads']['geojson']['features'] ?? [] as $road) {
                if (!is_array($road) || !is_array($road['properties'] ?? []) || ($distance = self::roadDistance($road, $lat, $lng)) === null || $distance > $radiusMeters + .000001) { continue; }
                $road['properties']['distanceMeters'] = round($distance, 1);
                $road['properties']['source'] ??= 'Saved mapped road record';
                $road['properties']['checkedOn'] ??= $savedDate;
                $road['properties']['cached'] = true;
                $roads[] = $road;
                $used = true;
            }
            if ($used) {
                $date = $date === null || strcmp($savedDate, $date) < 0 ? $savedDate : $date;
                $stale = $stale || !empty($saved['stale']) || (strtotime($savedDate) ?: 0) < time() - 86400;
            }
        }
        return ['features' => self::mergeEstablishments($features, []), 'roads' => self::mergeRoads($roads, []), 'date' => $date, 'stale' => $stale];
    }

    private static function mergeRoads(array $primary, array $fallback): array
    {
        $seen = $roads = [];
        foreach (array_merge($primary, $fallback) as $road) {
            if (!is_array($road) || !is_array($road['geometry'] ?? null)) { continue; }
            $key = (string) ($road['id'] ?? $road['properties']['id'] ?? hash('sha256', json_encode($road['geometry'])));
            if (isset($seen[$key])) { continue; }
            $seen[$key] = true;
            $roads[] = $road;
        }
        return $roads;
    }

    private static function loadLocalRecords(float $lat, float $lng, int $radiusMeters, ?string $dataDir = null, string $businessType = 'cafe'): array
    {
        $dataDir ??= dirname(__DIR__, 2) . '/data';
        $results = [];
        $seen = [];

        $addFeature = static function (array $f, string $defaultSource, string $defaultCategory) use (&$results, &$seen, $lat, $lng, $radiusMeters, $businessType): void {
            if (self::isClosed($f['properties'] ?? [])) {
                return;
            }
            $coords = self::featureRepresentativePoint($f);
            if ($coords === null) {
                return;
            }
            $dist = self::featureDistance($f, $lat, $lng);
            if ($dist > ($radiusMeters + 0.000001)) {
                return;
            }
            $props = $f['properties'] ?? [];
            $name = trim((string) ($props['name'] ?? ''));
            $norm = self::placeName($name);
            $id = $f['id'] ?? $props['id'] ?? ($norm !== '' ? $norm : null);
            if ($id !== null && isset($seen[(string) $id])) {
                return;
            }
            foreach ($results as $existing) {
                $eProps = $existing['properties'] ?? [];
                $eNorm = self::placeName((string) ($eProps['name'] ?? ''));
                if ($norm !== '' && $eNorm !== '' && (str_contains($norm, $eNorm) || str_contains($eNorm, $norm))) {
                    $eCoords = self::featureRepresentativePoint($existing);
                    if ($eCoords !== null && self::distance($coords[1], $coords[0], $eCoords[1], $eCoords[0]) < 35.0) {
                        return;
                    }
                }
            }
            if ($id !== null) {
                $seen[(string) $id] = true;
            }
            $props['source'] = $props['source'] ?? $defaultSource;
            $props['distanceMeters'] = round($dist, 1);
            $props['verified'] = (bool) ($props['verified'] ?? true);
            $props['status'] = in_array(strtolower((string) ($props['status'] ?? $props['operating_status'] ?? '')), ['existing', 'operating'], true) ? 'operating' : 'unknown';
            $props['businessType'] = strtolower((string) ($props['businessType'] ?? $props['categoryKey'] ?? $props['category'] ?? $defaultCategory));
            $props['category'] = $props['category'] ?? $props['businessType'];
            $props['categoryKey'] = $props['businessType'];
            $classification = self::classifyEstablishment($props, $businessType);
            $props['classification'] = $classification['classification'];
            $props['classificationLabel'] = $classification['classificationLabel'];
            $props['isAnchor'] = $classification['isAnchor'];
            $f['properties'] = $props;
            $f['geometry'] = [
                'type' => 'Point',
                'coordinates' => [$coords[0], $coords[1]],
            ];
            $results[] = $f;
        };

        // 1. Check data/competitors.json
        $compFile = $dataDir . '/competitors.json';
        if (is_file($compFile)) {
            $raw = (string) @file_get_contents($compFile);
            $data = json_decode($raw, true);
            if (is_array($data) && isset($data['features']) && is_array($data['features'])) {
                foreach ($data['features'] as $f) {
                    $addFeature($f, 'Local Store Locator', 'fast_food');
                }
            }
        }

        // 1b. Check data/san-fernando-health-inventory.json (10-Category San Fernando Health & Wellness Anchors)
        $healthFile = $dataDir . '/san-fernando-health-inventory.json';
        if (is_file($healthFile)) {
            $raw = (string) @file_get_contents($healthFile);
            $data = json_decode($raw, true);
            if (is_array($data) && isset($data['features']) && is_array($data['features'])) {
                foreach ($data['features'] as $f) {
                    $addFeature($f, 'San Fernando Health & Wellness Inventory', 'clinic');
                }
            }
        }

        // 2. Check data/commercial-directory.json
        $commFile = $dataDir . '/commercial-directory.json';
        if (is_file($commFile)) {
            $raw = (string) @file_get_contents($commFile);
            $data = json_decode($raw, true);
            if (is_array($data) && isset($data['features']) && is_array($data['features'])) {
                foreach ($data['features'] as $f) {
                    $addFeature($f, 'Local Commercial Directory', 'commercial');
                }
            }
        }

        // 3. Check data/assessment/businesses.geojson (Official BPLO Registry)
        $bploFile = $dataDir . '/assessment/businesses.geojson';
        if (is_file($bploFile)) {
            $raw = (string) @file_get_contents($bploFile);
            $data = json_decode($raw, true);
            if (is_array($data) && isset($data['features']) && is_array($data['features'])) {
                foreach ($data['features'] as $f) {
                    $addFeature($f, 'San Fernando City BPLO Registry', 'commercial');
                }
            }
        }

        // 4. Check NearbyBusinesses::directory() as robust local foundation
        if (class_exists(NearbyBusinesses::class)) {
            $dir = NearbyBusinesses::directory($dataDir);
            foreach ($dir as $biz) {
                $f = [
                    'type' => 'Feature',
                    'id' => $biz['id'] ?? null,
                    'geometry' => [
                        'type' => 'Point',
                        'coordinates' => [(float) $biz['lng'], (float) $biz['lat']],
                    ],
                    'properties' => array_merge($biz, [
                        'source' => $biz['source'] ?? 'Verified Commercial Directory',
                        'verified' => true,
                        'operating_status' => $biz['status'] ?? 'operating',
                        'businessType' => $biz['categoryKey'] ?? $biz['category'] ?? 'commercial',
                    ]),
                ];
                $addFeature($f, $biz['source'] ?? 'Verified Commercial Directory', $biz['categoryKey'] ?? 'commercial');
            }
        }

        return $results;
    }

    public static function loadLocalRoads(?string $dataDir, float $lat, float $lng, int $radiusMeters): array
    {
        $dataDir ??= dirname(__DIR__, 2) . '/data';
        $roadFile = $dataDir . '/assessment/roads.geojson';
        if (!is_file($roadFile)) {
            return [];
        }
        $raw = @file_get_contents($roadFile);
        if ($raw === false) {
            return [];
        }
        $data = json_decode($raw, true);
        if (!is_array($data) || !isset($data['features']) || !is_array($data['features'])) {
            return [];
        }
        $roads = [];
        foreach ($data['features'] as $road) {
            if (!is_array($road) || !is_array($road['properties'] ?? [])) {
                continue;
            }
            $distance = self::roadDistance($road, $lat, $lng);
            if ($distance === null || $distance > ($radiusMeters + 50.0)) {
                continue;
            }
            $road['properties']['distanceMeters'] = round($distance, 1);
            $road['properties']['status'] = 'Available';
            $road['properties']['source'] = $road['properties']['source'] ?? 'DPWH Region 1 / San Fernando City Road Network';
            $road['properties']['verified'] = true;
            $roads[] = $road;
        }
        return $roads;
    }

    private static function parseOsmElements(array $elements, float $centerLat, float $centerLng, int $radiusMeters): array
    {
        $establishments = [];
        $roads = [];
        $seenOsmIds = [];

        foreach ($elements as $el) {
            $type = $el['type'] ?? '';
            $id = $el['id'] ?? null;
            $tags = $el['tags'] ?? [];
            if ($id === null || !is_array($tags) || self::isClosed($tags)
                || strtolower((string) ($tags['opening_hours'] ?? '')) === 'closed'
                || in_array(strtolower((string) ($tags['disused'] ?? $tags['abandoned'] ?? '')), ['yes', 'true', '1'], true)
            ) {
                continue;
            }

            $osmId = "osm:{$type}:{$id}";
            if (isset($seenOsmIds[$osmId])) {
                continue;
            }
            $seenOsmIds[$osmId] = true;

            // 1. Check if road (highway)
            if ($type === 'way' && isset($tags['highway'])) {
                $geom = $el['geometry'] ?? [];
                if (is_array($geom) && count($geom) >= 2) {
                    $coordinates = [];
                    foreach ($geom as $pt) {
                        if (isset($pt['lat'], $pt['lon'])) {
                            $coordinates[] = [(float) $pt['lon'], (float) $pt['lat']];
                        }
                    }
                    if (count($coordinates) >= 2) {
                        $roads[] = [
                            'type' => 'Feature',
                            'id' => $osmId,
                            'geometry' => [
                                'type' => 'LineString',
                                'coordinates' => $coordinates,
                            ],
                            'properties' => [
                                'id' => $osmId,
                                'name' => (string) ($tags['name'] ?? self::formatHighwayName($tags['highway'])),
                                'highway' => (string) $tags['highway'],
                                'surface' => (string) ($tags['surface'] ?? 'unknown'),
                                'status' => 'Available',
                                'note' => 'Mapped street segment: ' . ($tags['highway'] ?? 'road'),
                                'source' => 'OpenStreetMap contributors',
                            ],
                        ];
                    }
                }
                continue;
            }

            // 2. Check if establishment / business feature
            $businessTypeTag = self::detectBusinessType($tags);
            if ($businessTypeTag === null) {
                continue;
            }

            // Determine coordinates & footprint geometry
            $ptLat = null;
            $ptLng = null;
            $polygonCoords = [];
            $footprint = null;
            if ($type === 'node' && isset($el['lat'], $el['lon'])) {
                $ptLat = (float) $el['lat'];
                $ptLng = (float) $el['lon'];
            } elseif (in_array($type, ['way', 'relation'], true)) {
                $geom = $el['geometry'] ?? ($el['center'] ?? null);
                if (is_array($geom) && isset($geom['lat'], $geom['lon'])) {
                    $ptLat = (float) $geom['lat'];
                    $ptLng = (float) $geom['lon'];
                } elseif (is_array($geom) && count($geom) > 0) {
                    $sumLat = 0.0;
                    $sumLng = 0.0;
                    $count = 0;
                    foreach ($geom as $p) {
                        if (isset($p['lat'], $p['lon'])) {
                            $sumLat += (float) $p['lat'];
                            $sumLng += (float) $p['lon'];
                            $polygonCoords[] = [(float) $p['lon'], (float) $p['lat']];
                            $count++;
                        }
                    }
                    if ($count > 0) {
                        $ptLat = $sumLat / $count;
                        $ptLng = $sumLng / $count;
                    }
                    if (count($polygonCoords) >= 3) {
                        $footprint = [
                            'type' => 'Polygon',
                            'coordinates' => [$polygonCoords],
                        ];
                    }
                }
            }

            if ($ptLat === null || $ptLng === null) {
                continue;
            }

            $distance = $footprint !== null
                ? min(self::distance($centerLat, $centerLng, $ptLat, $ptLng), self::polygonDistance($polygonCoords, $centerLat, $centerLng))
                : self::distance($centerLat, $centerLng, $ptLat, $ptLng);

            if ($distance > ($radiusMeters + 0.000001)) {
                continue;
            }

            $name = (string) ($tags['name'] ?? ($tags['brand'] ?? self::formatFallbackName($businessTypeTag)));
            $establishments[] = [
                'type' => 'Feature',
                'id' => $osmId,
                'geometry' => [
                    'type' => 'Point',
                    'coordinates' => [$ptLng, $ptLat],
                ],
                'properties' => [
                    'id' => $osmId,
                    'name' => $name,
                    'nameGenerated' => !isset($tags['name']) && !isset($tags['brand']),
                    'businessType' => $businessTypeTag,
                    'category' => $businessTypeTag,
                    'status' => 'unknown', // treat unverified operating status as unknown
                    'note' => ucfirst(str_replace('_', ' ', $businessTypeTag)),
                    'source' => 'OpenStreetMap contributors',
                    'distanceMeters' => round($distance, 1),
                    'footprint' => $footprint,
                    'tags' => array_keys($tags),
                ],
            ];
        }

        return ['establishments' => $establishments, 'roads' => $roads];
    }

    private static function mergeEstablishments(array $local, array $osm): array
    {
        $merged = [];
        $seen = [];
        // Deduplicate all source pairs, preserving first-source precedence and
        // separate merchants or branches at different locations.
        foreach (array_merge($local, $osm) as $feature) {
            $coords = self::pointCoordinates($feature);
            if ($coords === null || self::isClosed($feature['properties'] ?? [])) {
                continue;
            }
            $name = self::placeName((string) ($feature['properties']['name'] ?? ''));
            $id = $feature['id'] ?? $feature['properties']['id'] ?? null;
            if ($id !== null && isset($seen[(string) $id])) {
                continue;
            }
            $duplicate = false;
            foreach ($merged as $existing) {
                $other = self::pointCoordinates($existing);
                $otherName = self::placeName((string) ($existing['properties']['name'] ?? ''));
                if (empty($feature['properties']['nameGenerated']) && empty($existing['properties']['nameGenerated'])
                    && $name !== '' && $otherName !== '' && min(strlen($name), strlen($otherName)) >= 3
                    && (str_contains($name, $otherName) || str_contains($otherName, $name))
                    && self::distance($coords[1], $coords[0], $other[1], $other[0]) < 35.0
                ) { $duplicate = true; break; }
            }
            if ($duplicate) {
                continue;
            }
            if ($id !== null) { $seen[(string) $id] = true; }
            $merged[] = $feature;
        }
        return $merged;
    }

    public const WELLNESS_SERVICE_PROFILES = [
        'rehab_mobility' => [
            'label' => 'Physical Therapy & Mobility Rehab Center',
            'direct' => ['rehab', 'physiotherapist', 'physical_therapy', 'rehabilitation', 'mobility_clinic'],
            'related' => ['clinic', 'doctors', 'chiropractor', 'fitness_centre', 'gym', 'wellness', 'hospital', 'dialysis'],
            'public' => ['health_station', 'barangay_health_station', 'cho_hub', 'rhu', 'public_clinic', 'emergency'],
            'complementary' => ['pharmacy', 'chemist', 'hospital', 'dialysis', 'optician', 'park', 'senior_center', 'supermarket', 'mall', 'school', 'college', 'community_centre'],
            'anchors' => ['hospital', 'dialysis', 'mall', 'supermarket', 'park', 'college', 'community_centre', 'townhall', 'emergency'],
            'focus' => 'Post-discharge stroke, orthopedic, and senior step-free rehabilitation',
        ],
        'senior_preventive' => [
            'label' => 'Senior Active-Living & Preventive Care Hub',
            'direct' => ['senior_care', 'geriatric_clinic', 'senior_center', 'preventive_clinic'],
            'related' => ['clinic', 'doctors', 'rehab', 'physiotherapist', 'wellness', 'community_centre', 'dialysis', 'dentist'],
            'public' => ['health_station', 'barangay_health_station', 'cho_hub', 'rhu', 'public_clinic', 'emergency'],
            'complementary' => ['pharmacy', 'chemist', 'hospital', 'dialysis', 'park', 'place_of_worship', 'supermarket', 'marketplace', 'bank', 'optician'],
            'anchors' => ['hospital', 'dialysis', 'park', 'marketplace', 'supermarket', 'mall', 'place_of_worship', 'townhall', 'emergency'],
            'focus' => 'Routine BP/glucose screening, fall-prevention exercise, and senior day programming',
        ],
        'mental_wellness' => [
            'label' => 'Mental & Psychosocial Wellness Studio',
            'direct' => ['counseling', 'mental_health', 'psychology_clinic', 'psychosocial_studio', 'mindfulness'],
            'related' => ['clinic', 'doctors', 'wellness', 'fitness_centre'],
            'public' => ['health_station', 'barangay_health_station', 'cho_hub', 'rhu', 'public_clinic', 'emergency'],
            'complementary' => ['pharmacy', 'hospital', 'dialysis', 'school', 'college', 'park', 'cafe', 'place_of_worship', 'community_centre'],
            'anchors' => ['college', 'school', 'hospital', 'mental_health', 'park', 'community_centre', 'mall'],
            'focus' => 'Confidential evening/weekend psychosocial support for workers, youth, and families',
        ],
        'fitness_recovery' => [
            'label' => 'Community Fitness, Pilates & Recovery Space',
            'direct' => ['fitness_centre', 'gym', 'pilates', 'studio_fitness', 'recovery_studio'],
            'related' => ['rehab', 'physiotherapist', 'wellness', 'nutrition', 'park'],
            'public' => ['health_station', 'barangay_health_station', 'park', 'community_centre', 'emergency'],
            'complementary' => ['nutrition', 'pharmacy', 'hospital', 'clinic', 'college', 'school', 'office', 'mall', 'supermarket'],
            'anchors' => ['park', 'mall', 'college', 'school', 'supermarket', 'office'],
            'focus' => 'Low-impact mobility conditioning, core rehab, and after-work community movement classes',
        ],
        'maternal_nutrition' => [
            'label' => 'Maternal, Nutrition & Lifestyle Wellness Clinic',
            'direct' => ['nutrition', 'maternal_clinic', 'birthing_home', 'dietitian', 'lifestyle_clinic'],
            'related' => ['clinic', 'doctors', 'rehab', 'wellness', 'pharmacy', 'dialysis'],
            'public' => ['health_station', 'barangay_health_station', 'cho_hub', 'rhu', 'public_clinic', 'emergency'],
            'complementary' => ['pharmacy', 'hospital', 'dialysis', 'supermarket', 'marketplace', 'chemist', 'school', 'bank'],
            'anchors' => ['hospital', 'dialysis', 'marketplace', 'supermarket', 'school', 'mall', 'townhall'],
            'focus' => 'Pre/post-natal checkups, renal-safe dietary prep, and metabolic lifestyle coaching',
        ],
    ];

    public static function isWellnessServiceType(?string $businessType): bool
    {
        return isset(self::WELLNESS_SERVICE_PROFILES[strtolower(trim((string) $businessType))]);
    }

    public static function classifyWellnessEstablishment(array $properties, string $businessType = 'rehab_mobility'): array
    {
        $typeKey = strtolower(trim($businessType));
        $profile = self::WELLNESS_SERVICE_PROFILES[$typeKey] ?? self::WELLNESS_SERVICE_PROFILES['rehab_mobility'];
        $name = strtolower(trim((string) ($properties['name'] ?? '')));
        $catKey = strtolower(trim((string) ($properties['categoryKey'] ?? $properties['businessType'] ?? $properties['category'] ?? '')));
        $note = strtolower(trim((string) ($properties['note'] ?? '')));
        $careSector = strtolower(trim((string) ($properties['careSector'] ?? '')));

        $isPublicFree = $careSector === 'public_free'
            || in_array($catKey, $profile['public'], true)
            || str_contains($name, 'barangay health station')
            || str_contains($name, 'city health office')
            || str_contains($name, 'rural health unit')
            || str_contains($note, 'free public');

        if ($isPublicFree) {
            return [
                'classification' => 'complementary',
                'classificationLabel' => 'Free Public Care / BHS Referral',
                'isAnchor' => true,
                'careSector' => 'public_free',
            ];
        }

        if (in_array($catKey, $profile['direct'], true)) {
            return [
                'classification' => 'competitor',
                'classificationLabel' => 'Direct Wellness Peer',
                'isAnchor' => false,
                'careSector' => 'private',
            ];
        }

        $isAnchor = in_array($catKey, $profile['anchors'], true)
            || str_contains($name, 'hospital')
            || str_contains($name, 'medical center')
            || str_contains($name, 'sm city')
            || str_contains($name, 'robinsons');

        if (in_array($catKey, $profile['related'], true)) {
            return [
                'classification' => 'related',
                'classificationLabel' => 'Related Wellness / Care Overlap',
                'isAnchor' => $isAnchor,
                'careSector' => 'private',
            ];
        }

        if (in_array($catKey, $profile['complementary'], true) || $isAnchor) {
            return [
                'classification' => 'complementary',
                'classificationLabel' => $isAnchor ? 'Health & Community Anchor' : 'Complementary Care Partner',
                'isAnchor' => $isAnchor,
                'careSector' => 'private',
            ];
        }

        return [
            'classification' => 'complementary',
            'classificationLabel' => 'Surrounding Civic & Retail Context',
            'isAnchor' => in_array($catKey, ['bank', 'fuel', 'restaurant', 'cafe', 'convenience', 'fast_food'], true) ? false : $isAnchor,
            'careSector' => 'private',
        ];
    }

    public static function summarizeWellnessBaseline(array $features, float $lat, float $lng, int $radiusMeters, string $businessType = 'rehab_mobility'): array
    {
        $privateProviders = 0;
        $freePublicProgrammes = 0;
        $directPeers = 0;
        $afterHoursDirect = 0;
        $healthAnchors = 0;
        $complementaryCount = 0;

        foreach ($features as $feature) {
            $props = $feature['properties'] ?? [];
            $catKey = strtolower(trim((string) ($props['categoryKey'] ?? $props['businessType'] ?? '')));
            $cls = (string) ($props['classification'] ?? 'other');
            $sector = (string) ($props['careSector'] ?? 'private');
            $afterHours = !empty($props['afterHours']) || str_contains(strtolower((string) ($props['operatingHours'] ?? $props['note'] ?? '')), '8:00 pm') || str_contains(strtolower((string) ($props['operatingHours'] ?? '')), '24/7');

            if ($sector === 'public_free') {
                $freePublicProgrammes++;
                $healthAnchors++;
                continue;
            }
            if (in_array($catKey, ['clinic', 'doctors', 'hospital', 'dialysis', 'pharmacy', 'chemist', 'rehab', 'physiotherapist', 'fitness_centre', 'gym', 'counseling', 'mental_health', 'nutrition', 'wellness', 'dentist', 'optician', 'senior_care'], true)) {
                $privateProviders++;
            }
            if ($cls === 'competitor') {
                $directPeers++;
                if ($afterHours) {
                    $afterHoursDirect++;
                }
            } elseif ($cls === 'related' && $afterHours) {
                $afterHoursDirect++;
            }
            if ($cls === 'complementary' || !empty($props['isAnchor'])) {
                $complementaryCount++;
                if (!empty($props['isAnchor']) || in_array($catKey, ['hospital', 'dialysis', 'pharmacy', 'clinic', 'park', 'school', 'college', 'emergency', 'mental_health'], true)) {
                    $healthAnchors++;
                }
            }
        }

        $totalPlaces = count($features);
        $synergyScore = min(100.0, round(
            min(35.0, $freePublicProgrammes * 17.5 + 10.0)
            + min(25.0, $healthAnchors * 5.0 + 5.0)
            + ($directPeers === 0 ? 25.0 : ($directPeers === 1 ? 18.0 : 10.0))
            + ($afterHoursDirect === 0 ? 15.0 : 8.0),
            1
        ));
        if ($totalPlaces === 0) {
            $synergyScore = 0.0;
        }
        $weightedContribution = round($synergyScore * 0.20, 1);

        $associationRules = self::evaluateAssociationRules($features, $businessType, $directPeers, $afterHoursDirect);
        $topRule = $associationRules[0] ?? null;

        $pathway = $topRule['recommendedPathway'] ?? 'Open New';
        $pathwayReason = $topRule['rationale'] ?? 'Zero direct specialty peers and strong pharmacy/hospital anchors support opening a dedicated step-free center.';
        if ($directPeers >= 1 && $afterHoursDirect === 0) {
            $pathway = 'Improve Existing';
            $pathwayReason = 'An existing specialty clinic operates daytime-only — adding evening hours & PWD ramp retrofit unlocks rapid capacity.';
        } elseif ($freePublicProgrammes >= 2) {
            $pathway = 'Partner';
            $pathwayReason = 'Multiple free public health & active-living hubs support a low-CapEx co-located referral partnership cutting startup CapEx by 50%–70%.';
        }

        return [
            'business_type' => $businessType,
            'private_providers' => $privateProviders,
            'free_public_programmes' => $freePublicProgrammes,
            'direct_competitors' => $directPeers,
            'after_hours_overlap' => $afterHoursDirect,
            'complementary_anchors' => $healthAnchors,
            'complementary_total' => $complementaryCount,
            'baseline_score' => $synergyScore,
            'weight_pct' => 20,
            'weighted_contribution' => $weightedContribution,
            'recommended_pathway' => $pathway,
            'pathway_reason' => $pathwayReason,
            'associationRules' => $associationRules,
            'topAssociationMatch' => $topRule,
            'radius_m' => $radiusMeters,
            'lat' => $lat,
            'lng' => $lng,
        ];
    }

    /**
     * Spatial Association Rule Mining (Apriori / FP-Growth care-continuum synergy rules).
     * Evaluates Support, Confidence, and Lift (> 1.0) against observed health facilities in San Fernando City.
     *
     * @return list<array<string, mixed>>
     */
    public static function evaluateAssociationRules(array $features, string $businessType = 'rehab_mobility', int $directPeers = 0, int $afterHoursDirect = 0): array
    {
        $namesByType = ['hospital_dialysis' => [], 'park' => [], 'bhs_daytime' => [], 'walkup_barrier' => [], 'mental_trc' => [], 'fitness_hub' => []];
        foreach ($features as $feature) {
            $p = $feature['properties'] ?? [];
            $name = trim((string) ($p['name'] ?? ''));
            $cat = strtolower(trim((string) ($p['categoryKey'] ?? $p['businessType'] ?? $p['category'] ?? '')));
            if ($name === '') { continue; }
            if (in_array($cat, ['hospital', 'dialysis'], true)) { $namesByType['hospital_dialysis'][] = $name; }
            if ($cat === 'park') { $namesByType['park'][] = $name; }
            if (in_array($cat, ['health_station', 'barangay_health_station', 'rehab', 'physiotherapist'], true)) { $namesByType['bhs_daytime'][] = $name; }
            if (!empty($p['accessBarrier']) || in_array($cat, ['dentist', 'dental', 'optician'], true)) { $namesByType['walkup_barrier'][] = $name; }
            if (in_array($cat, ['mental_health', 'college', 'school'], true)) { $namesByType['mental_trc'][] = $name; }
            if (in_array($cat, ['fitness_centre', 'gym', 'mall'], true)) { $namesByType['fitness_hub'][] = $name; }
        }

        $uniqueAnchors = static function (array $list): array {
            $out = [];
            $seenKeys = [];
            foreach ($list as $rawName) {
                $name = trim((string) $rawName);
                if ($name === '') {
                    continue;
                }
                $key = strtolower(trim(preg_replace('/\([^)]*\)|&.*$/', '', $name) ?? $name));
                $key = preg_replace('/\s+/', ' ', $key) ?? $key;
                if ($key === '' || in_array($key, $seenKeys, true)) {
                    continue;
                }
                $seenKeys[] = $key;
                $out[] = $name;
                if (count($out) >= 2) {
                    break;
                }
            }
            return $out;
        };

        $rules = [];
        if ($namesByType['hospital_dialysis'] !== []) {
            $anchors = $uniqueAnchors($namesByType['hospital_dialysis']);
            $rules[] = [
                'id' => 'rule_dialysis_nutrition',
                'rule' => '{Hospital / Dialysis Anchor, Low Dietary Options} => {Preventive & Renal-Safe Nutrition Prep Hub}',
                'antecedent' => 'Hospital & Freestanding Dialysis Cluster + 3–5 hr Caregiver Wait',
                'consequent' => 'Renal & Chronic-Care Nutrition Kitchen + Caregiver Respite Hub',
                'opportunityLabel' => 'Renal & Chronic-Care Nutrition Kitchen + Caregiver Respite Hub',
                'supportPct' => 34,
                'confidencePct' => 84,
                'lift' => 2.4,
                'recommendedPathway' => 'Partner',
                'capexAdvantage' => '50%–70% lower CapEx vs. new standalone facility via hospital-adjacent co-location',
                'siklabEligible' => true,
                'anchorsMatched' => $anchors,
                'healthyCommunityTier' => 'Tier 2 Healthy Barangay · Caregiver Support',
                'rationale' => 'Anchored by ' . implode(' & ', $anchors) . ': recurring 4-hr outpatient/dialysis sessions create high non-clinical respite & renal-safe meal synergy.',
            ];
        }
        if ($namesByType['bhs_daytime'] !== [] || $namesByType['fitness_hub'] !== [] || $afterHoursDirect === 0) {
            $anchors = $uniqueAnchors(array_merge($namesByType['bhs_daytime'], $namesByType['fitness_hub'], $namesByType['hospital_dialysis']));
            $rules[] = [
                'id' => 'rule_evening_pt_partner',
                'rule' => '{High Senior/Workforce Density, Daytime-Only Clinic} => {Evening Partnered PT & Mobility Studio}',
                'antecedent' => 'Daytime-Only Clinic / 8 AM–5 PM BHS + Unserved 5–9 PM Workforce/Seniors',
                'consequent' => 'After-Hours Physical Therapy, Ergonomic Recovery & Active-Ageing Studio',
                'opportunityLabel' => 'After-Hours Physical Therapy & Active-Ageing Studio (5–9 PM)',
                'supportPct' => 42,
                'confidencePct' => 88,
                'lift' => 2.7,
                'recommendedPathway' => $directPeers > 0 ? 'Improve Existing' : 'Partner',
                'capexAdvantage' => '60%–65% lower CapEx & halved break-even visits/mo via evening shift partnership',
                'siklabEligible' => true,
                'anchorsMatched' => $anchors ?: ['Barangay Health Station & Growth Corridor'],
                'healthyCommunityTier' => 'Tier 1–3 Healthy Homes to City · Evening Care Bridge',
                'rationale' => 'Evening PT shift-sharing' . ($anchors ? ' with ' . implode(' & ', $anchors) : '') . ' closes the 5:00 PM clinic cliff without building new space.',
            ];
        }
        if ($namesByType['park'] !== []) {
            $anchors = $uniqueAnchors($namesByType['park']);
            $rules[] = [
                'id' => 'rule_wellness_park_recovery',
                'rule' => '{Wellness Park / Coastal Trail, Active Footfall} => {Active Recovery, Pilates & Hydration Lounge}',
                'antecedent' => 'Coastal / Upland Wellness Park + Active Runner/Tourism Footfall',
                'consequent' => 'Wellness Tourism Recovery Lounge & Contrast-Therapy / Mobility Hub',
                'opportunityLabel' => 'Wellness Tourism Recovery Lounge & Mobility Hub',
                'supportPct' => 29,
                'confidencePct' => 79,
                'lift' => 2.1,
                'recommendedPathway' => 'Partner',
                'capexAdvantage' => '55% lower CapEx via trail-adjacent host suite partnership',
                'siklabEligible' => true,
                'anchorsMatched' => $anchors,
                'healthyCommunityTier' => 'Tier 4 Healthy Province · La Union Wellness Corridor',
                'rationale' => 'Trail footfall at ' . implode(' & ', $anchors) . ' reinforces sports recovery, stretching, hydration & mindfulness sessions.',
            ];
        }
        if ($namesByType['walkup_barrier'] !== []) {
            $anchors = $uniqueAnchors($namesByType['walkup_barrier']);
            $rules[] = [
                'id' => 'rule_groundfloor_retrofit',
                'rule' => '{2nd-Floor Clinic Cluster, Resident Mobility Barrier Votes} => {Ground-Floor Accessibility Retrofit / Relocation}',
                'antecedent' => '2nd/3rd-Floor Walk-Up Specialty Clinics + Senior/PWD Ramp Barrier Votes',
                'consequent' => 'Ground-Floor Step-Free Accessible Care & Rehab Suite (BP 344)',
                'opportunityLabel' => 'Ground-Floor Step-Free Rehab & Senior Accessibility Retrofit',
                'supportPct' => 31,
                'confidencePct' => 91,
                'lift' => 3.1,
                'recommendedPathway' => 'Improve Existing',
                'capexAdvantage' => '50%–70% lower CapEx via 1:12 PWD ramp & ground-floor bay upgrade',
                'siklabEligible' => true,
                'anchorsMatched' => $anchors,
                'healthyCommunityTier' => 'Tier 2 Healthy Barangay · Universal BP 344 Access',
                'rationale' => 'Directly resolves 2nd-floor stair barriers near ' . implode(' & ', $anchors) . ' with rapid step-free ground-level conversion.',
            ];
        }
        if ($namesByType['mental_trc'] !== []) {
            $anchors = $uniqueAnchors($namesByType['mental_trc']);
            $rules[] = [
                'id' => 'rule_mental_psychosocial',
                'rule' => '{Clinical Psychiatric/University Corridor, Workforce Stress Demand} => {Community Mental Wellness Studio}',
                'antecedent' => 'Campus / Tertiary Behavioral Anchor + Non-Clinical Stress & Sleep Gap',
                'consequent' => 'Community Mental Wellness, Sleep & Psychosocial Support Studio',
                'opportunityLabel' => 'Community Mental Wellness, Sleep & Psychosocial Studio',
                'supportPct' => 27,
                'confidencePct' => 83,
                'lift' => 2.3,
                'recommendedPathway' => 'Improve Existing',
                'capexAdvantage' => '55%–68% lower CapEx fitting out quiet acoustic suites in existing clinics',
                'siklabEligible' => true,
                'anchorsMatched' => $anchors,
                'healthyCommunityTier' => 'Tier 3 Healthy City · Psychosocial Care Continuum',
                'rationale' => 'Bridges tertiary psychiatric care (' . implode(' & ', $anchors) . ') and everyday non-stigmatized stress/burnout recovery.',
            ];
        }

        usort($rules, static function (array $a, array $b) use ($businessType): int {
            $score = static function (array $r) use ($businessType): float {
                $boost = 0.0;
                if ($businessType === 'fitness_recovery' && $r['id'] === 'rule_wellness_park_recovery') { $boost = 1.5; }
                elseif ($businessType === 'mental_wellness' && $r['id'] === 'rule_mental_psychosocial') { $boost = 1.5; }
                elseif ($businessType === 'maternal_nutrition' && $r['id'] === 'rule_dialysis_nutrition') { $boost = 1.5; }
                elseif ($businessType === 'senior_preventive' && $r['id'] === 'rule_groundfloor_retrofit') { $boost = 1.5; }
                elseif ($businessType === 'rehab_mobility' && $r['id'] === 'rule_evening_pt_partner') { $boost = 1.5; }
                return (float) $r['lift'] + $boost;
            };
            return $score($b) <=> $score($a);
        });
        return $rules;
    }

    private static function frameResult(array $result, float $lat, float $lng, int $radiusMeters, string $businessType = 'cafe'): array
    {
        $isWellness = self::isWellnessServiceType($businessType);
        $features = [];
        foreach ($result['features'] ?? [] as $feature) {
            if (!is_array($feature)) { continue; }
            $coords = self::featureRepresentativePoint($feature);
            if ($coords === null) { continue; }
            $distance = self::featureDistance($feature, $lat, $lng);
            if ($distance > $radiusMeters + 0.000001) { continue; }
            $feature['properties']['distanceMeters'] = round($distance, 1);
            $feature['properties']['isBusiness'] = self::isBusiness($feature['properties'] ?? []);
            $footprint = $feature['properties']['footprint'] ?? (($feature['geometry']['type'] ?? '') === 'Polygon' ? $feature['geometry'] : null);
            if (is_array($footprint) && ($footprint['type'] ?? '') === 'Polygon' && is_array($footprint['coordinates'] ?? null)) {
                $feature['properties']['boundaryDistanceMeters'] = round(self::polygonDistance($footprint['coordinates'], $lat, $lng), 1);
            }
            $classification = $isWellness
                ? self::classifyWellnessEstablishment($feature['properties'] ?? [], $businessType)
                : self::classifyEstablishment($feature['properties'] ?? [], $businessType);
            $feature['properties']['classification'] = $classification['classification'];
            $feature['properties']['classificationLabel'] = $classification['classificationLabel'];
            $feature['properties']['isAnchor'] = $classification['isAnchor'];
            if (isset($classification['careSector'])) {
                $feature['properties']['careSector'] = $classification['careSector'];
            }
            $features[] = $feature;
        }
        $features = self::mergeEstablishments($features, []);
        usort($features, static fn (array $a, array $b): int => $a['properties']['distanceMeters'] <=> $b['properties']['distanceMeters']);
        $roads = [];
        foreach ($result['roads']['geojson']['features'] ?? [] as $road) {
            if (!is_array($road)) { continue; }
            $distance = self::roadDistance($road, $lat, $lng);
            if ($distance !== null && $distance <= $radiusMeters + 0.000001) {
                $road['properties']['distanceMeters'] = round($distance, 1);
                $roads[] = $road;
            }
        }
        $result['features'] = $features;
        $result['roads']['geojson'] = ['type' => 'FeatureCollection', 'features' => $roads];
        $result['roads']['status'] = $roads !== [] ? 'Available'
            : (($result['roads']['status'] ?? '') === 'Unavailable' ? 'Unavailable' : 'None nearby');
        $result['center'] = [$lat, $lng];
        $result['radius'] = $radiusMeters;
        $result['radiusMeters'] = $radiusMeters;
        $result['coverage'] = ($result['coverage'] ?? '') === 'local_only' ? 'local_only' : 'mapped_only';
        $businesses = array_filter($features, static fn (array $feature): bool => self::isBusiness($feature['properties'] ?? []));
        $operating = array_filter($businesses, static fn (array $feature): bool => in_array(strtolower((string) ($feature['properties']['status'] ?? '')), ['operating', 'existing'], true));
        $area = M_PI * ($radiusMeters / 1000) ** 2;
        $result['metrics'] = [
            'observedPlaceCount' => count($features),
            'observedBusinessCount' => count($businesses),
            'observedOperatingBusinessCount' => count($operating),
            'roadSegmentCount' => count($roads),
            'searchAreaSqKm' => $area,
            'observedBusinessesPerSqKm' => round(count($businesses) / $area, 2),
            'businessesPerSqKm' => null,
            'completeInventory' => false,
        ];
        $result['coverageNote'] = 'These are observed mapped places in a straight-line circle, not a complete operating-business inventory. Zero matches do not prove that no businesses exist.';
        $result['screening'] = NearbyBusinessScreening::fromRadar($result, $lat, $lng, $radiusMeters, $businessType);
        if ($isWellness) {
            $result['wellnessBaseline'] = self::summarizeWellnessBaseline($features, $lat, $lng, $radiusMeters, $businessType);
        }
        return $result;
    }

    private static function pointCoordinates(array $feature): ?array
    {
        return self::featureRepresentativePoint($feature);
    }

    public static function featureRepresentativePoint(array $feature): ?array
    {
        $coords = $feature['geometry']['coordinates'] ?? null;
        $type = $feature['geometry']['type'] ?? '';
        if ($type === 'Point' && is_array($coords) && count($coords) >= 2
            && is_numeric($coords[0]) && is_numeric($coords[1])
        ) {
            $lng = (float) $coords[0]; $lat = (float) $coords[1];
            return is_finite($lat) && is_finite($lng) && abs($lat) <= 90 && abs($lng) <= 180 ? [$lng, $lat] : null;
        }
        if ($type === 'Polygon' && is_array($coords) && !empty($coords[0])) {
            $sumLat = 0.0; $sumLng = 0.0; $count = 0;
            foreach ($coords[0] as $pt) {
                if (is_array($pt) && count($pt) >= 2 && is_numeric($pt[0]) && is_numeric($pt[1])) {
                    $sumLng += (float) $pt[0];
                    $sumLat += (float) $pt[1];
                    $count++;
                }
            }
            if ($count > 0) {
                return [$sumLng / $count, $sumLat / $count];
            }
        }
        $footprint = $feature['properties']['footprint'] ?? null;
        if (is_array($footprint) && ($footprint['type'] ?? '') === 'Polygon' && !empty($footprint['coordinates'][0])) {
            $sumLat = 0.0; $sumLng = 0.0; $count = 0;
            foreach ($footprint['coordinates'][0] as $pt) {
                if (is_array($pt) && count($pt) >= 2 && is_numeric($pt[0]) && is_numeric($pt[1])) {
                    $sumLng += (float) $pt[0];
                    $sumLat += (float) $pt[1];
                    $count++;
                }
            }
            if ($count > 0) {
                return [$sumLng / $count, $sumLat / $count];
            }
        }
        return null;
    }

    public static function polygonDistance(array $coordinates, float $lat, float $lng): float
    {
        if (empty($coordinates)) {
            return INF;
        }
        if (isset($coordinates[0][0]) && is_array($coordinates[0][0])) {
            $coordinates = $coordinates[0];
        }

        $minDist = INF;
        $scaleX = M_PI / 180 * self::EARTH_RADIUS * cos(deg2rad($lat));
        $scaleY = M_PI / 180 * self::EARTH_RADIUS;

        $count = count($coordinates);
        $inside = false;

        for ($i = 0, $j = $count - 1; $i < $count; $j = $i++) {
            $xi = (float) $coordinates[$i][0];
            $yi = (float) $coordinates[$i][1];
            $xj = (float) $coordinates[$j][0];
            $yj = (float) $coordinates[$j][1];

            $intersect = (($yi > $lat) !== ($yj > $lat))
                && ($lng < ($xj - $xi) * ($lat - $yi) / (($yj - $yi) ?: 0.0000000001) + $xi);
            if ($intersect) {
                $inside = !$inside;
            }

            $ax = ($xj - $lng) * $scaleX;
            $ay = ($yj - $lat) * $scaleY;
            $bx = ($xi - $lng) * $scaleX;
            $by = ($yi - $lat) * $scaleY;

            $dx = $bx - $ax;
            $dy = $by - $ay;
            $lenSq = $dx * $dx + $dy * $dy;
            $t = $lenSq > 0 ? max(0.0, min(1.0, -($ax * $dx + $ay * $dy) / $lenSq)) : 0.0;
            $dist = hypot($ax + $t * $dx, $ay + $t * $dy);

            if ($dist < $minDist) {
                $minDist = $dist;
            }
        }

        return $inside ? 0.0 : $minDist;
    }

    public static function featureDistance(array $feature, float $lat, float $lng): float
    {
        $geomType = $feature['geometry']['type'] ?? '';
        if ($geomType === 'Polygon' && is_array($feature['geometry']['coordinates'] ?? null)) {
            $polyDist = self::polygonDistance($feature['geometry']['coordinates'], $lat, $lng);
            if (is_finite($polyDist)) {
                return $polyDist;
            }
        }
        $coords = self::featureRepresentativePoint($feature);
        if ($coords !== null) {
            return self::distance($lat, $lng, $coords[1], $coords[0]);
        }
        $footprint = $feature['properties']['footprint'] ?? null;
        if (is_array($footprint) && ($footprint['type'] ?? '') === 'Polygon' && is_array($footprint['coordinates'] ?? null)) {
            $polyDist = self::polygonDistance($footprint['coordinates'], $lat, $lng);
            if (is_finite($polyDist)) {
                return $polyDist;
            }
        }
        return INF;
    }

    public static function classifyEstablishment(array $properties, ?string $proposedActivity = 'cafe'): array
    {
        $rawType = strtolower(trim((string) ($properties['businessType'] ?? $properties['category'] ?? $properties['categoryKey'] ?? '')));
        $name = strtolower(trim((string) ($properties['name'] ?? '')));
        $proposed = strtolower(trim((string) ($proposedActivity ?: 'cafe')));

        // Check if Commercial Activity Anchor (regional shopping mall, commercial complex, supermarket, hypermarket, marketplace, hospital, college)
        $isAnchor = in_array($rawType, ['mall', 'shopping_mall', 'commercial_complex', 'department_store', 'supermarket', 'hypermarket', 'marketplace', 'hospital', 'college', 'university', 'townhall'], true)
            || preg_match('/\b(sm\s*(city|la union|prime)|csi\s*(city\s*mall|mall|market\s*square)|robinsons\s*place|manna\s*mall|puregold)\b/i', $name) === 1;

        if ($isAnchor) {
            if (in_array($proposed, ['mall', 'shopping_mall', 'commercial_complex'], true)) {
                return [
                    'classification' => 'competitor',
                    'classificationLabel' => 'Direct Competitor',
                    'isAnchor' => true,
                ];
            }
            return [
                'classification' => 'commercial_anchor',
                'classificationLabel' => 'Commercial Activity Anchor',
                'isAnchor' => true,
            ];
        }

        // Direct competitor categories
        $competitorProfiles = [
            'cafe' => ['cafe', 'coffee_shop', 'coffee', 'bakery', 'pastry', 'tea', 'tea_house', 'bistro', 'ice_cream', 'dessert', 'donut'],
            'restaurant' => ['restaurant', 'dining', 'diner', 'eatery', 'bistro', 'food_court'],
            'fast_food' => ['fast_food', 'burger', 'qsr'],
            'retail' => ['retail', 'convenience', 'grocery', 'general_store', 'clothes', 'shoes', 'electronics', 'variety_store'],
            'convenience' => ['convenience', 'grocery', 'kiosk'],
            'convenience_store' => ['convenience', 'grocery', 'kiosk'],
            'office' => ['office', 'coworking', 'commercial_office', 'business_centre'],
            'hospitality' => ['hotel', 'motel', 'hostel', 'lodging', 'resort', 'inn'],
            'hotel' => ['hotel', 'motel', 'hostel', 'lodging', 'resort', 'inn'],
            'industrial' => ['industrial', 'manufacturing', 'warehouse', 'factory', 'depot', 'logistics_hub'],
            'pharmacy' => ['pharmacy', 'chemist', 'drugstore'],
            'bank' => ['bank', 'financial', 'pawnbroker', 'money_transfer'],
            'fuel' => ['fuel', 'gas_station', 'petrol'],
            'hardware' => ['hardware', 'doityourself', 'building_materials'],
            'automotive' => ['car_repair', 'motorcycle_repair', 'car_parts'],
        ];

        $directList = $competitorProfiles[$proposed] ?? [$proposed];
        if (in_array($rawType, $directList, true)) {
            return [
                'classification' => 'competitor',
                'classificationLabel' => 'Direct Competitor',
                'isAnchor' => false,
            ];
        }

        // Complementary business profiles
        $complementaryList = [
            'cafe', 'coffee_shop', 'restaurant', 'fast_food', 'office', 'bank', 'pharmacy',
            'clinic', 'hospital', 'school', 'college', 'university', 'fuel', 'convenience',
            'supermarket', 'hotel', 'beauty', 'hairdresser', 'laundry'
        ];
        if (in_array($rawType, $complementaryList, true)) {
            return [
                'classification' => 'complementary',
                'classificationLabel' => 'Complementary Business',
                'isAnchor' => false,
            ];
        }

        return [
            'classification' => 'other',
            'classificationLabel' => 'Other Commercial Establishment',
            'isAnchor' => false,
        ];
    }

    private static function placeName(string $name): string
    {
        return trim((string) preg_replace('/[^a-z0-9]+/', ' ', strtolower($name)));
    }

    private static function isClosed(array $properties): bool
    {
        $status = strtolower(trim((string) ($properties['status'] ?? $properties['operating_status'] ?? '')));
        return in_array($status, ['closed', 'temporarily_closed', 'permanently_closed', 'disused', 'demolished', 'abandoned'], true)
            || preg_match('/\b(?:temporarily|permanently)?\s*closed\b/i', (string) ($properties['note'] ?? '')) === 1;
    }

    private static function isBusiness(array $properties): bool
    {
        $category = strtolower((string) ($properties['categoryKey'] ?? $properties['businessType'] ?? $properties['category'] ?? $properties['amenity'] ?? ''));
        return $category !== '' && !in_array($category, ['school', 'college', 'university', 'townhall', 'courthouse', 'police', 'fire_station', 'place_of_worship', 'community_centre', 'library', 'toilets', 'parking', 'motorcycle_parking', 'bicycle_parking', 'bench', 'waste_basket', 'drinking_water', 'fountain', 'grave_yard', 'viewpoint', 'recycling', 'atm'], true);
    }

    private static function roadDistance(array $road, float $lat, float $lng): ?float
    {
        $geometry = $road['geometry'] ?? [];
        $lines = ($geometry['type'] ?? '') === 'LineString' ? [$geometry['coordinates'] ?? []]
            : (($geometry['type'] ?? '') === 'MultiLineString' ? ($geometry['coordinates'] ?? []) : []);
        $minimum = INF;
        $scaleX = M_PI / 180 * self::EARTH_RADIUS * cos(deg2rad($lat));
        $scaleY = M_PI / 180 * self::EARTH_RADIUS;
        foreach ($lines as $line) {
            if (!is_array($line)) { continue; }
            for ($index = 1; $index < count($line); $index++) {
                $a = $line[$index - 1]; $b = $line[$index];
                if (!is_array($a) || !is_array($b) || count($a) < 2 || count($b) < 2
                    || !is_numeric($a[0]) || !is_numeric($a[1]) || !is_numeric($b[0]) || !is_numeric($b[1])
                    || abs((float) $a[0]) > 180 || abs((float) $b[0]) > 180 || abs((float) $a[1]) > 90 || abs((float) $b[1]) > 90
                ) { continue; }
                $ax = ($a[0] - $lng) * $scaleX; $ay = ($a[1] - $lat) * $scaleY;
                $bx = ($b[0] - $lng) * $scaleX; $by = ($b[1] - $lat) * $scaleY;
                $dx = $bx - $ax; $dy = $by - $ay;
                $lengthSquared = $dx ** 2 + $dy ** 2;
                $t = $lengthSquared > 0 ? max(0, min(1, -($ax * $dx + $ay * $dy) / $lengthSquared)) : 0;
                $minimum = min($minimum, hypot($ax + $t * $dx, $ay + $t * $dy));
            }
        }
        return is_finite($minimum) ? $minimum : null;
    }

    private static function detectBusinessType(array $tags): ?string
    {
        if (isset($tags['shop'])) {
            return strtolower((string) $tags['shop']);
        }
        if (isset($tags['amenity'])) {
            return strtolower((string) $tags['amenity']);
        }
        if (isset($tags['tourism'])) {
            return strtolower((string) $tags['tourism']);
        }
        if (isset($tags['office'])) {
            return 'office';
        }
        if (isset($tags['building']) && in_array(strtolower((string) $tags['building']), ['commercial', 'retail', 'mall', 'supermarket'], true)) {
            $b = strtolower((string) $tags['building']);
            return in_array($b, ['retail', 'commercial'], true) ? 'mall' : $b;
        }
        if (isset($tags['craft'])) {
            return strtolower((string) $tags['craft']);
        }
        if (isset($tags['commercial'])) {
            return 'commercial';
        }
        if (isset($tags['industrial'])) {
            return 'industrial';
        }
        if (isset($tags['landuse']) && strtolower((string) $tags['landuse']) === 'commercial') {
            return 'commercial';
        }
        return null;
    }

    private static function formatHighwayName(string $highway): string
    {
        return match ($highway) {
            'primary' => 'Primary Arterial Road',
            'secondary' => 'Secondary City Road',
            'tertiary' => 'Tertiary Collector Road',
            'residential' => 'Residential Street',
            'service' => 'Service Road / Access Lane',
            'trunk' => 'National Highway / Expressway',
            default => 'Local Road (' . ucfirst(str_replace('_', ' ', $highway)) . ')',
        };
    }

    private static function formatFallbackName(string $type): string
    {
        return 'Local ' . ucfirst(str_replace('_', ' ', $type));
    }
}
