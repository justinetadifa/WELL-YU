<?php
declare(strict_types=1);

namespace App\Support;

/** Read-only discovery from published listings and the locally recorded facility inventory. */
final class PropertyWellnessDiscovery
{
    public const RADIUS_M = 2000;
    public const SOURCES_CHECKED_ON = '2026-11-18';

    private const CATEGORIES = [
        'hospital' => 'Hospitals',
        'dialysis' => 'Dialysis centers',
        'rehab' => 'Physical therapy & rehabilitation',
        'mental_health' => 'Mental health & recovery',
        'pharmacy' => 'Pharmacies',
        'dentist' => 'Dental & specialist clinics',
        'fitness_centre' => 'Fitness & movement',
        'park' => 'Parks & outdoor wellness',
        'emergency' => 'Emergency services',
        'health_station' => 'Community health stations',
    ];
    private const HEALTH_ANCHORS = ['hospital', 'dialysis', 'rehab', 'mental_health', 'health_station'];
    private const OFFICIAL_SOURCES = [
        'hlth-itrmc' => ['name' => 'PhilHealth 2026 contracted facilities', 'url' => 'https://www.philhealth.gov.ph/partners/providers/facilities/contracted/ZBENEFITS_CONTRACTED_02282026.pdf'],
        'hlth-lorma' => ['name' => 'Lorma Medical Center contact directory', 'url' => 'https://lmc.lorma.org/contact-us/'],
        'hlth-bethany' => ['name' => 'Bethany Hospital patient guide', 'url' => 'https://bethanyhospitalinc.com/patients'],
        'hlth-lumed' => ['name' => 'LUMED Hospital contact directory', 'url' => 'https://lumed.com.ph/contact'],
        'hlth-doh-trc' => ['name' => 'DOH-TRC La Union directory', 'url' => 'https://trclu.doh.gov.ph/directory/'],
    ];

    /**
     * Optional inventory enables isolated deterministic tests; normal callers read the existing JSON.
     * No database connection, network lookup, score update or persistence occurs here.
     */
    public static function snapshot(array $properties, ?array $inventory = null): array
    {
        if ($inventory === null) {
            $path = dirname(__DIR__, 2) . '/data/san-fernando-health-inventory.json';
            $raw = is_file($path) ? file_get_contents($path) : false;
            $decoded = $raw === false ? null : json_decode($raw, true);
            $inventory = is_array($decoded) ? $decoded : [];
        }
        $facilities = self::facilities($inventory);
        $categories = [];
        foreach (self::CATEGORIES as $id => $label) {
            $count = count(array_filter($facilities, static fn (array $facility): bool => $facility['category'] === $id));
            if ($count > 0) { $categories[] = ['id' => $id, 'label' => $label, 'count' => $count]; }
        }
        $ideas = self::ideas();
        $opportunities = [];
        $eligible = 0;
        $missingCoordinates = 0;
        $seenProperties = [];
        foreach ($properties as $property) {
            if (!is_array($property) || !self::eligible($property)) { continue; }
            $id = (int) ($property['id'] ?? 0);
            if ($id <= 0 || isset($seenProperties[$id])) { continue; }
            $seenProperties[$id] = true;
            $eligible++;
            $coordinates = self::coordinates($property['lat'] ?? null, $property['lng'] ?? null);
            if ($coordinates === null) { $missingCoordinates++; continue; }
            $nearby = [];
            foreach ($facilities as $facility) {
                if ($facility['lat'] === null || $facility['lng'] === null) { continue; }
                $distance = self::distance($coordinates[0], $coordinates[1], $facility['lat'], $facility['lng']);
                if ($distance <= self::RADIUS_M) { $nearby[] = ['facility' => $facility, 'distance' => $distance]; }
            }
            $anchors = array_values(array_filter($nearby, static fn (array $entry): bool => in_array($entry['facility']['category'], self::HEALTH_ANCHORS, true)));
            if ($anchors === []) { continue; }
            usort($anchors, static fn (array $a, array $b): int => ($a['distance'] <=> $b['distance']) ?: strcmp($a['facility']['id'], $b['facility']['id']));
            $nearest = $anchors[0];
            $idea = self::ideaFor($nearest['facility']['category'], $ideas);
            $opportunities[] = [
                'property_id' => $id,
                'title' => (string) ($property['name'] ?? $property['propertyName'] ?? 'Property listing'),
                'barangay' => (string) ($property['barangay'] ?? ''),
                'category' => (string) ($property['category'] ?? ''),
                'subcategory' => (string) ($property['subcategory'] ?? ''),
                'image' => (string) ($property['imageUrl'] ?? $property['imagePath'] ?? ''),
                'price' => $property['price'] ?? null,
                'salePrice' => $property['salePrice'] ?? $property['price'] ?? null,
                'leasePrice' => $property['leasePrice'] ?? null,
                'listingPurpose' => (string) ($property['listingPurpose'] ?? 'sale'),
                'leasePeriod' => (string) ($property['leasePeriod'] ?? 'month'),
                'leasePriceUnit' => (string) ($property['leasePriceUnit'] ?? 'total'),
                'lat' => $coordinates[0],
                'lng' => $coordinates[1],
                'radius_m' => self::RADIUS_M,
                'nearby_count' => count($nearby),
                'nearest_facility' => array_intersect_key($nearest['facility'], array_flip(['id', 'name', 'category', 'barangay', 'source_url'])),
                'nearest_distance_m' => (int) round($nearest['distance']),
                'idea_id' => $idea['id'],
                'idea_title' => $idea['title'],
                'rationale' => 'Near a recorded ' . strtolower(self::CATEGORIES[$nearest['facility']['category']]) . ' location; explore complementary services with the operator.',
                'coordinate_note' => 'Approximate straight-line proximity from recorded map points; entrances and travel routes are unverified.',
                // Preserve full precision for ordering; remove it before returning JSON.
                '_distance' => $nearest['distance'],
            ];
        }
        usort($opportunities, static fn (array $a, array $b): int => ($a['_distance'] <=> $b['_distance']) ?: (($b['nearby_count'] <=> $a['nearby_count']) ?: ($a['property_id'] <=> $b['property_id'])));
        $candidateCount = count($opportunities);
        $opportunities = array_slice($opportunities, 0, 6);
        foreach ($opportunities as $index => &$opportunity) {
            $opportunity['rank'] = $index + 1;
            unset($opportunity['_distance']);
        }
        unset($opportunity);
        return [
            'inventory_date' => self::dateLabel($inventory['generated'] ?? null),
            'sources_checked_on' => self::SOURCES_CHECKED_ON,
            'radius_m' => self::RADIUS_M,
            'facility_count' => count($facilities),
            'official_source_count' => count(array_filter($facilities, static fn (array $facility): bool => $facility['source_url'] !== '')),
            'eligible_property_count' => $eligible,
            'missing_coordinates_count' => $missingCoordinates,
            'candidate_count' => $candidateCount,
            'categories' => $categories,
            'facilities' => $facilities,
            'ideas' => $ideas,
            'opportunities' => $opportunities,
            'methodology' => 'Available, approved listings within 2 km of a recorded hospital, dialysis center, rehabilitation service, mental health service or community health station. Ordered by nearest health anchor, then nearby facility count and listing ID. Counts include all recorded wellness categories within 2 km. No financial score is assigned.',
            'notice' => 'The local inventory was recorded on ' . (self::dateLabel($inventory['generated'] ?? null) ?? 'an unspecified date') . '. Linked official sources confirm facility identity or address only; availability, capacity, exact map points and operating hours need confirmation. Business concepts are screening ideas; validate demand, competition, site suitability and licensing before investing.',
            'empty_message' => $eligible === 0 ? 'No available, approved properties are recorded yet. Add or review a listing to build this shortlist.' : ($facilities === [] ? 'The facility inventory is unavailable. Nearby opportunities will appear when recorded facility locations are available.' : 'No mapped, available, approved listing is within 2 km of a recorded health anchor. Add precise listing coordinates or explore the full property list.'),
        ];
    }

    private static function eligible(array $property): bool
    {
        return strtolower(trim((string) ($property['approvalState'] ?? ''))) === 'approved'
            && in_array(strtolower(trim((string) ($property['status'] ?? ''))), ['available', 'active', 'open'], true)
            && empty($property['deletedAt']) && empty($property['archivedAt'])
            && empty($property['isDeleted']) && empty($property['isArchived']);
    }

    private static function facilities(array $inventory): array
    {
        $facilities = [];
        foreach (($inventory['features'] ?? []) as $feature) {
            if (!is_array($feature) || !is_array($feature['properties'] ?? null)) { continue; }
            $entry = $feature['properties'];
            $id = (string) ($feature['id'] ?? $entry['id'] ?? '');
            $name = trim((string) ($entry['name'] ?? ''));
            $category = (string) ($entry['category'] ?? '');
            if ($id === '' || $name === '' || !isset(self::CATEGORIES[$category]) || isset($facilities[$id])) { continue; }
            if (in_array(strtolower((string) ($entry['status'] ?? '')), ['closed', 'deleted', 'inactive'], true)) { continue; }
            $point = $feature['geometry']['coordinates'] ?? [];
            $coordinates = ($feature['geometry']['type'] ?? '') === 'Point' ? self::coordinates($point[1] ?? null, $point[0] ?? null) : null;
            $source = self::OFFICIAL_SOURCES[$id] ?? null;
            $address = (string) ($entry['address'] ?? '');
            $barangay = (string) ($entry['barangay'] ?? '');
            $coordinateNote = 'Local inventory map point; exact entrance coordinates have not been verified.';
            // The operator directory contradicts the inventory's Tanqui location.
            // Do not silently retain that point in distance calculations.
            if ($id === 'hlth-lumed') {
                $address = 'Bucaneg St., Catbangen, San Fernando City, La Union';
                $barangay = 'Catbangen';
                $coordinates = null;
                $coordinateNote = 'Operator address differs from the local map point. Proximity is withheld pending coordinate verification.';
            }
            $facilities[$id] = [
                'id' => $id, 'name' => $name, 'category' => $category,
                'category_label' => self::CATEGORIES[$category],
                'barangay' => $barangay, 'address' => $address,
                'lat' => $coordinates[0] ?? null, 'lng' => $coordinates[1] ?? null,
                'source' => $source['name'] ?? (string) ($entry['source'] ?? $inventory['source'] ?? 'Local facility inventory'),
                'source_url' => $source['url'] ?? '',
                'source_checked_on' => $source ? self::SOURCES_CHECKED_ON : null,
                'inventory_date' => self::dateLabel($inventory['generated'] ?? null),
                'evidence_label' => $source ? 'Official source linked' : 'Local inventory record',
                'status_label' => 'Recorded facility',
                'coordinate_note' => $coordinateNote,
                'care_sector' => (string) ($entry['careSector'] ?? ''),
            ];
        }
        $facilities = array_values($facilities);
        usort($facilities, static fn (array $a, array $b): int => ((int) ($b['source_url'] !== '') <=> (int) ($a['source_url'] !== '')) ?: strcmp($a['name'], $b['name']));
        return $facilities;
    }

    private static function coordinates(mixed $lat, mixed $lng): ?array
    {
        if (is_bool($lat) || is_bool($lng) || !is_numeric($lat) || !is_numeric($lng)) { return null; }
        $lat = (float) $lat; $lng = (float) $lng;
        if (!is_finite($lat) || !is_finite($lng) || abs($lat) > 90 || abs($lng) > 180) { return null; }
        return [$lat, $lng];
    }

    private static function distance(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $a = sin(deg2rad($lat2 - $lat1) / 2) ** 2 + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin(deg2rad($lng2 - $lng1) / 2) ** 2;
        return 6371000 * 2 * atan2(sqrt(min(1, max(0, $a))), sqrt(max(0, 1 - $a)));
    }

    private static function dateLabel(mixed $value): ?string
    {
        if (!is_string($value) || !preg_match('/^\d{4}-\d{2}-\d{2}$/D', $value)) { return null; }
        $date = \DateTimeImmutable::createFromFormat('!Y-m-d', $value);
        return $date && $date->format('Y-m-d') === $value ? $value : null;
    }

    private static function ideaFor(string $category, array $ideas): array
    {
        $id = match ($category) {
            'dialysis' => 'nutrition',
            'mental_health' => 'counseling',
            'rehab' => 'mobility',
            'health_station' => 'prevention',
            default => 'rehabilitation',
        };
        foreach ($ideas as $idea) { if ($idea['id'] === $id) { return $idea; } }
        return $ideas[0];
    }

    private static function ideas(): array
    {
        return [
            ['id' => 'rehabilitation', 'title' => 'Community rehabilitation studio', 'category' => 'rehabilitation', 'description' => 'An accessible space for guided recovery, physical therapy and everyday movement.', 'fit' => 'Explore referral relationships near hospitals and rehabilitation services.', 'validation' => 'Confirm licensed practitioners, existing referral capacity, step-free access and clinical requirements.', 'anchor_categories' => ['hospital', 'rehab']],
            ['id' => 'nutrition', 'title' => 'Nutrition & healthy meal hub', 'category' => 'nutrition', 'description' => 'Dietitian-led consultations and meals designed around documented customer needs.', 'fit' => 'Explore complementary support for patients, staff and caregivers near care centers.', 'validation' => 'Interview operators and customers; confirm food safety, qualified dietitians and individual dietary requirements.', 'anchor_categories' => ['dialysis', 'hospital']],
            ['id' => 'counseling', 'title' => 'Counseling & wellbeing space', 'category' => 'mental_wellbeing', 'description' => 'Private consultation rooms with flexible appointments for community wellbeing.', 'fit' => 'Explore a community service alongside established mental health and primary care providers.', 'validation' => 'Confirm licensed professionals, privacy, safeguarding, referral protocols and actual local demand.', 'anchor_categories' => ['mental_health', 'health_station']],
            ['id' => 'mobility', 'title' => 'Senior mobility & movement studio', 'category' => 'movement', 'description' => 'An inclusive space for supervised strength, balance and low-impact activities.', 'fit' => 'Explore accessible ground-floor space near community health and rehabilitation providers.', 'validation' => 'Assess ramps, toilets, fall prevention, staff qualifications and participant demand.', 'anchor_categories' => ['rehab', 'health_station']],
            ['id' => 'prevention', 'title' => 'Preventive wellness practice', 'category' => 'preventive_care', 'description' => 'A small practice for preventive advice and follow-up services with qualified providers.', 'fit' => 'Explore service hours and referral partnerships that complement existing community care.', 'validation' => 'Confirm service gaps with the CHO, avoid duplicating free programs and verify applicable licenses.', 'anchor_categories' => ['health_station', 'hospital']],
            ['id' => 'caregiver', 'title' => 'Caregiver respite & support hub', 'category' => 'other', 'description' => 'A comfortable place for caregivers to rest, connect and access practical support.', 'fit' => 'Explore a suitable site near hospital and recurring outpatient care locations.', 'validation' => 'Interview caregivers, check safe access, verify occupancy rules and clarify any care-service licensing.', 'anchor_categories' => ['hospital', 'dialysis']],
        ];
    }
}
