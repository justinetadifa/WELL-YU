<?php
declare(strict_types=1);

namespace App\Support;

require_once __DIR__ . '/PropertyWellnessDiscovery.php';

/** A read-only public view of recorded facilities, listed sites and exploration concepts. */
final class WellnessInvestmentHub
{
    /** Optional inputs keep source, eligibility and missing-data checks independent of the database. */
    public static function snapshot(array $publicProperties, ?array $sourceData = null, ?array $inventory = null): array
    {
        $sourceData ??= self::readJson('wellness-investment-sources.json');
        $checkedOn = self::date($sourceData['checked_on'] ?? null);
        $featured = self::featured($sourceData['featured'] ?? [], $checkedOn);
        usort($featured, static fn (array $a, array $b): int => strcasecmp($a['name'], $b['name']));
        $inventory ??= self::readJson('san-fernando-health-inventory.json');
        // An explicitly flagged address conflict must not retain a misleading map point.
        foreach ($featured as $record) {
            if (!$record['exclude_inventory_coordinates']) { continue; }
            if (!is_array($inventory['features'] ?? null)) { continue; }
            foreach ($inventory['features'] as &$feature) {
                if (!is_array($feature)) { continue; }
                if (($feature['id'] ?? $feature['properties']['id'] ?? '') === $record['id']) {
                    $feature['geometry'] = null;
                }
            }
            unset($feature);
        }
        $result = PropertyWellnessDiscovery::snapshot($publicProperties, $inventory);
        $featuredById = array_column($featured, null, 'id');
        foreach ($result['facilities'] as &$facility) {
            $official = $featuredById[$facility['id']] ?? null;
            if ($official === null) { continue; }
            foreach (['name', 'address', 'barangay'] as $field) {
                if ($official[$field] !== '') { $facility[$field] = $official[$field]; }
            }
            $facility['source'] = $official['source_title'];
            $facility['source_url'] = $official['source_url'];
            $facility['source_checked_on'] = $checkedOn;
            $facility['evidence_label'] = 'Official source linked';
        }
        unset($facility);

        $categoryCounts = array_column($result['categories'], 'count', 'id');
        $categoryLabels = array_column($result['categories'], 'label', 'id');
        $ideaSources = self::ideaSources($sourceData['idea_sources'] ?? []);
        foreach ($result['ideas'] as &$idea) {
            $categories = [];
            $count = 0;
            foreach ($idea['anchor_categories'] as $category) {
                $categoryCount = (int) ($categoryCounts[$category] ?? 0);
                $count += $categoryCount;
                $categories[] = ['id' => $category, 'label' => $categoryLabels[$category] ?? str_replace('_', ' ', $category), 'count' => $categoryCount];
            }
            $source = $ideaSources[$idea['id']] ?? null;
            $idea['evidence_count'] = $count;
            $idea['evidence_categories'] = $categories;
            $idea['evidence_basis'] = $count > 0
                ? $count . ' recorded care ' . ($count === 1 ? 'anchor' : 'anchors') . ' in this concept\'s related service categories. Explore how a new service could complement existing providers.'
                : 'No matching care anchor is recorded in the available inventory. Gather local service evidence before selecting a site.';
            $idea['evidence_note'] = 'Existing services provide context; this is not evidence of an unmet market gap or a financial return.';
            $idea['source_url'] = $source['source_url'] ?? '';
            $idea['source_title'] = $source['source_title'] ?? 'Local San Fernando facility inventory';
            $idea['source_summary'] = $source['evidence_summary'] ?? '';
            $idea['next_steps'] = self::nextSteps($idea['id'], $idea['validation']);
            $idea['demand_status'] = 'Requires local demand validation';
        }
        unset($idea);

        $result['featured_facilities'] = $featured;
        $result['featured_count'] = count($featured);
        if ($checkedOn !== null) { $result['sources_checked_on'] = $checkedOn; }
        $result['official_source_count'] = count(array_filter($result['facilities'], static fn (array $facility): bool => $facility['source_url'] !== ''));
        $result['investment_modes'] = self::investmentModes();
        $result['source_metadata'] = [
            'checked_on' => $checkedOn,
            'selection_label' => 'Featured health and wellness facilities',
            'featured_basis' => 'Featured facilities have linked official identity, address or service information. They are not ranked by clinical quality, revenue or investment performance.',
            'facility_notice' => 'The broader directory is a dated local inventory. Confirm current services and availability with each operator.',
            'investment_notice' => 'No equity offer, franchise, fundraising round or facility sale has been verified. Provider links are for exploratory contact; site cards refer to separate property listings.',
            'idea_notice' => 'Business concepts are suggestions to investigate. Local inventory counts describe recorded supply and do not establish unmet demand.',
        ];
        $result['limitations'] = [
            'Featured facilities are an editorial selection supported by official sources, not a citywide top-business ranking.',
            'Facility identity and services do not establish permission to invest in the operator.',
            'Public facilities require the appropriate public authority for any collaboration; they are not presented as private equity opportunities.',
            'Property proximity is approximate straight-line distance from recorded points, not travel time or confirmed site suitability.',
            'Business demand, licensing, access, fit-out costs and financial feasibility require separate validation.',
        ];
        return $result;
    }

    private static function readJson(string $name): array
    {
        $path = dirname(__DIR__, 2) . '/data/' . $name;
        $raw = is_file($path) ? file_get_contents($path) : false;
        $data = $raw === false ? null : json_decode($raw, true);
        return is_array($data) ? $data : [];
    }

    private static function featured(mixed $records, ?string $checkedOn): array
    {
        if (!is_array($records)) { return []; }
        $featured = [];
        foreach ($records as $record) {
            if (!is_array($record)) { continue; }
            $id = trim((string) ($record['id'] ?? ''));
            $name = trim((string) ($record['name'] ?? ''));
            $sourceUrl = self::url($record['source_url'] ?? null);
            if ($id === '' || $name === '' || $sourceUrl === '' || isset($featured[$id])) { continue; }
            $sector = in_array($record['sector'] ?? '', ['public', 'private'], true) ? $record['sector'] : 'not_confirmed';
            $services = is_array($record['services'] ?? null) ? array_values(array_unique(array_filter(array_map(static fn (mixed $service): string => is_string($service) ? trim($service) : '', $record['services'])))) : [];
            $featured[$id] = [
                'id' => $id,
                'name' => $name,
                'category' => trim((string) ($record['category'] ?? '')),
                'address' => trim((string) ($record['address'] ?? '')),
                'barangay' => trim((string) ($record['barangay'] ?? '')),
                'sector' => $sector,
                'services' => array_slice($services, 0, 8),
                'description' => trim((string) ($record['description'] ?? '')),
                'source_url' => $sourceUrl,
                'source_title' => trim((string) ($record['source_title'] ?? 'Official facility information')),
                'source_checked_on' => $checkedOn,
                'contact_url' => self::url($record['contact_url'] ?? null) ?: $sourceUrl,
                'service_source_url' => self::url($record['service_source_url'] ?? null) ?: $sourceUrl,
                'service_source_title' => trim((string) ($record['service_source_title'] ?? $record['source_title'] ?? 'Official facility information')),
                'evidence_note' => trim((string) ($record['evidence_note'] ?? 'Official source supports the displayed identity, address or listed services.')),
                'investment_note' => trim((string) ($record['investment_note'] ?? 'Discuss a possible collaboration directly with the responsible operator; no investment offer has been verified.')),
                'action_label' => $sector === 'private' ? 'Explore partnership' : 'Explore complementary services',
                'offer_status' => 'No investment offer verified',
                'exclude_inventory_coordinates' => ($record['exclude_inventory_coordinates'] ?? false) === true,
            ];
        }
        return array_values($featured);
    }

    private static function ideaSources(mixed $records): array
    {
        if (!is_array($records)) { return []; }
        $sources = [];
        foreach ($records as $record) {
            if (!is_array($record)) { continue; }
            $id = trim((string) ($record['idea_id'] ?? ''));
            $url = self::url($record['source_url'] ?? null);
            if ($id === '' || $url === '') { continue; }
            $sources[$id] = ['source_url' => $url, 'source_title' => trim((string) ($record['source_title'] ?? 'Official service information')), 'evidence_summary' => trim((string) ($record['evidence_summary'] ?? ''))];
        }
        return $sources;
    }

    private static function url(mixed $value): string
    {
        if (!is_string($value) || filter_var($value, FILTER_VALIDATE_URL) === false) { return ''; }
        $parts = parse_url($value);
        return is_array($parts) && strtolower((string) ($parts['scheme'] ?? '')) === 'https' && !isset($parts['user']) && !isset($parts['pass']) ? $value : '';
    }

    private static function date(mixed $value): ?string
    {
        if (!is_string($value) || !preg_match('/^\d{4}-\d{2}-\d{2}$/D', $value)) { return null; }
        $date = \DateTimeImmutable::createFromFormat('!Y-m-d', $value);
        return $date && $date->format('Y-m-d') === $value ? $value : null;
    }

    private static function nextSteps(string $id, string $validation): array
    {
        $specific = match ($id) {
            'rehabilitation' => 'Ask rehabilitation providers about referral needs, existing capacity and suitable service scope.',
            'nutrition' => 'Interview patients, staff and caregivers about meal needs, budgets and existing options.',
            'counseling' => 'Discuss appointment demand, privacy and referral arrangements with qualified local providers.',
            'mobility' => 'Speak with older residents and rehabilitation providers about access and participation needs.',
            'prevention' => 'Ask the City Health Office which services and hours would complement existing programs.',
            'caregiver' => 'Interview caregivers about practical needs, safe access and willingness to use a support space.',
            default => 'Interview intended users and local operators to establish a specific service need.',
        };
        return [$specific, $validation, 'Prepare a site-specific cost and operating plan, then confirm requirements with the responsible offices before committing funds.'];
    }

    private static function investmentModes(): array
    {
        return [
            ['id' => 'open', 'title' => 'Open a new facility', 'description' => 'Explore a publicly listed property for a complementary health or wellness service.', 'next_step' => 'Validate demand and permitted use, inspect access and utilities, and prepare a fit-out and operating budget.', 'action_label' => 'Explore available sites'],
            ['id' => 'improve', 'title' => 'Improve an existing service', 'description' => 'Discuss accessibility, equipment, space or operating-hour improvements with an existing provider.', 'next_step' => 'Confirm the operator\'s needs, authority and proposed scope before assessing costs or agreeing terms.', 'action_label' => 'Find a provider'],
            ['id' => 'partner', 'title' => 'Explore a partnership', 'description' => 'Start a conversation about shared space, referral support or complementary services.', 'next_step' => 'Contact the responsible operator or public authority; a directory entry does not mean funding or partnership is being offered.', 'action_label' => 'Explore provider contacts'],
        ];
    }
}
