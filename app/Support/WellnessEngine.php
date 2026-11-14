<?php
declare(strict_types=1);
namespace App\Support;

use InvalidArgumentException;

/** Independent, deterministic wellness methodology. No PDO or historical-score writes. */
final class WellnessEngine
{
    public const VERSION = 'welyu-wellness-1.0';
    public const CRITERIA = [
        'access_affordability' => 'Community access and affordability',
        'health_access' => 'Relevant health facility access',
        'active_mobility' => 'Walking, wheeling and entrance accessibility',
        'operating_readiness' => 'Infrastructure and operating readiness',
        'cost_feasibility' => 'Property and implementation costs',
        'market_context' => 'Services, competition and demand evidence',
    ];
    public const CATEGORIES = ['healthcare', 'mental_wellbeing', 'movement', 'nutrition', 'rehabilitation', 'preventive_care', 'public_space', 'other'];
    public const READINESS = ['zoning', 'permit', 'sanitation', 'environment', 'hazards'];
    public const BARRIERS = ['affordability', 'travel', 'hours', 'capacity', 'physical_access', 'other'];

    public static function isList(array $value): bool { return $value === [] || array_keys($value) === range(0, count($value) - 1); }

    public static function object(mixed $input): array
    {
        if (!is_array($input)) { throw new InvalidArgumentException('Use a structured object for observations and evidence.'); }
        return $input;
    }

    public static function number(mixed $value, float $min = 0, float $max = 1000000000000): ?float
    {
        if ($value === null || $value === '') { return null; }
        if (is_bool($value) || !is_numeric($value) || !is_finite((float) $value) || (float) $value < $min || (float) $value > $max) {
            throw new InvalidArgumentException('Enter a finite number between ' . $min . ' and ' . $max . '.');
        }
        return (float) $value;
    }

    public static function text(mixed $value, int $limit = 190, bool $required = true): string
    {
        if (!is_string($value) || mb_strlen(trim($value)) > $limit || preg_match('/[\x00-\x08\x0b\x0c\x0e-\x1f\x7f]/u', $value)) {
            throw new InvalidArgumentException('Enter valid text, no longer than ' . $limit . ' characters.');
        }
        $value = trim($value);
        if ($required && $value === '') { throw new InvalidArgumentException('Complete all required text fields.'); }
        return $value;
    }

    public static function date(mixed $value, bool $required = true): ?string
    {
        if (($value === '' || $value === null) && !$required) { return null; }
        if (!is_string($value)) { throw new InvalidArgumentException('A valid observation date is required.'); }
        $date = \DateTimeImmutable::createFromFormat('!Y-m-d', $value, new \DateTimeZone('Asia/Manila'));
        $today = (new \DateTimeImmutable('today', new \DateTimeZone('Asia/Manila')))->format('Y-m-d');
        if (!$date || $date->format('Y-m-d') !== $value || $value > $today) { throw new InvalidArgumentException('Use a valid collection date that is not in the future.'); }
        return $value;
    }

    public static function evidence(mixed $input, bool $required = false): array
    {
        $input = self::object($input);
        $source = self::text($input['source'] ?? '', 600, $required);
        $date = self::date($input['observed_on'] ?? null, $required || $source !== '');
        $url = self::text($input['url'] ?? '', 600, false);
        if ($url !== '' && (!filter_var($url, FILTER_VALIDATE_URL) || !in_array(strtolower((string) parse_url($url, PHP_URL_SCHEME)), ['http', 'https'], true))) { throw new InvalidArgumentException('Source URLs must use http or https.'); }
        return ['source' => $source, 'observed_on' => $date, 'url' => $url];
    }

    public static function configuration(array $input): array
    {
        $weights = $input['weights'] ?? []; $rules = self::object($input['rules'] ?? []); $normalized = []; $total = 0;
        if (!is_array($weights) || array_diff(array_keys($weights), array_keys(self::CRITERIA))) { throw new InvalidArgumentException('Use exactly the six wellness criteria.'); }
        foreach (self::CRITERIA as $key => $label) {
            $weight = self::number($weights[$key] ?? null, 0, 100);
            if ($weight === null) { throw new InvalidArgumentException('Configure all six weights; no validated defaults are assumed.'); }
            $total += $weight;
            $rule = self::object($rules[$key] ?? []);
            $min = self::number($rule['min'] ?? 0, -1000000000000);
            $max = self::number($rule['max'] ?? 100, -1000000000000);
            if ($min === null || $max === null || $max <= $min) { throw new InvalidArgumentException('Each normalization range must have a maximum above its minimum.'); }
            $direction = $rule['direction'] ?? 'maximize';
            if (!in_array($direction, ['maximize', 'minimize'], true)) { throw new InvalidArgumentException('Choose maximize or minimize.'); }
            $normalized[$key] = ['weight' => $weight, 'min' => $min, 'max' => $max, 'direction' => $direction, 'unit' => self::text($rule['unit'] ?? 'documented rating', 80)];
        }
        if (abs($total - 100) > 0.000001) { throw new InvalidArgumentException('Wellness weights must total 100%.'); }
        $status = $input['validation_status'] ?? 'provisional';
        if (!in_array($status, ['provisional', 'validated'], true)) { throw new InvalidArgumentException('Invalid configuration status.'); }
        $reference = self::text($input['validation_reference'] ?? '', 600, $status === 'validated');
        return ['name' => self::text($input['name'] ?? 'Provisional wellness profile'), 'methodology_version' => self::VERSION, 'criteria' => $normalized, 'validation_status' => $status, 'validation_reference' => $reference, 'missing_policy' => 'withhold composite until every criterion has a value, source and date; no weight redistribution'];
    }

    public static function assess(array $input, array $configuration): array
    {
        self::object($input['criteria'] ?? []); self::object($input['readiness'] ?? []);
        $criteria = $configuration['criteria'] ?? []; $rows = []; $missing = []; $weighted = 0.0; $reviewed = true;
        foreach (self::CRITERIA as $key => $label) {
            if (!isset($criteria[$key])) { throw new InvalidArgumentException('The scoring configuration is incomplete.'); }
            $rule = $criteria[$key]; $observation = self::object($input['criteria'][$key] ?? []);
            if (!in_array($observation['status'] ?? 'reported', ['reported', 'documented', 'reviewed'], true)) { throw new InvalidArgumentException('Invalid observation review status.'); }
            $value = self::number($observation['value'] ?? null, -1000000000000);
            $evidence = self::evidence($observation, false);
            $hasEvidence = $value !== null && $evidence['source'] !== '' && $evidence['observed_on'] !== null;
            $normalized = null;
            if (!$hasEvidence) { $missing[] = $key; }
            else {
                $normalized = max(0, min(100, (($value - $rule['min']) / ($rule['max'] - $rule['min'])) * 100));
                if ($rule['direction'] === 'minimize') { $normalized = 100 - $normalized; }
                $weighted += $normalized * $rule['weight'] / 100;
            }
            $reviewed = $reviewed && ($observation['status'] ?? 'reported') === 'reviewed';
            $rows[] = ['key' => $key, 'label' => $label, 'raw' => $value, 'normalized' => $normalized, 'weight' => $rule['weight'], 'contribution' => $normalized === null ? null : $normalized * $rule['weight'] / 100, 'rule' => $rule, 'evidence' => $evidence, 'status' => $observation['status'] ?? 'reported'];
        }
        $restrictions = []; $unresolved = []; $readiness = [];
        foreach (self::READINESS as $key) {
            $entry = self::object($input['readiness'][$key] ?? []);
            $status = $entry['status'] ?? 'unknown';
            if (!in_array($status, ['unknown', 'reported', 'reviewed', 'restricted'], true)) { throw new InvalidArgumentException('Invalid readiness status.'); }
            $evidence = self::evidence($entry, false);
            if ($status === 'restricted') { $restrictions[] = $key; }
            if ($status !== 'reviewed' || $evidence['source'] === '' || $evidence['observed_on'] === null) { $unresolved[] = $key; }
            $readiness[$key] = ['status' => $status, 'evidence' => $evidence, 'notes' => self::text($entry['notes'] ?? '', 2000, false)];
        }
        $mce = $missing === [] && $restrictions === [] ? round($weighted, 2) : null;
        $iai = $mce !== null && $unresolved === [] ? $mce : null;
        return [
            'methodology_version' => self::VERSION, 'mce_score' => $mce, 'iai_score' => $iai,
            'mce_formula' => 'sum(normalized criterion * weight / 100); all six evidenced observations required',
            'iai_formula' => 'same wellness MCE after all five readiness fields are reviewed with source and date; otherwise withheld',
            'decision_thresholds' => 'No automatic investment recommendation or validated pass threshold is supplied.',
            'status' => $restrictions !== [] ? 'restricted' : ($missing !== [] ? 'incomplete' : ($unresolved !== [] ? 'verification_required' : ($reviewed ? 'reviewed_within_scope' : 'evidence_complete'))),
            'validation_status' => ($configuration['validation_status'] ?? '') === 'validated' && $reviewed && $unresolved === [] ? 'reviewed' : 'provisional',
            'evidence_coverage_pct' => round((count(self::CRITERIA) - count($missing)) / count(self::CRITERIA) * 100),
            'criteria' => $rows, 'readiness' => $readiness, 'missing_criteria' => $missing, 'critical_restrictions' => $restrictions, 'unresolved_requirements' => $unresolved,
            'notice' => 'A wellness decision aid, not a forecast of customers, medical recommendation, official hazard clearance or permit. Ratings and configuration must be read with their sources and review scope.',
        ];
    }

    public static function scenario(array $input, ?array $baseline = null): array
    {
        $fields = ['price_per_visit_php', 'variable_cost_php', 'fixed_monthly_cost_php', 'startup_cost_php', 'capacity_per_week', 'weekly_hours', 'expected_monthly_customers'];
        $values = []; foreach ($fields as $field) { $values[$field] = self::number($input[$field] ?? null); }
        foreach (['price_per_visit_php', 'variable_cost_php', 'fixed_monthly_cost_php', 'startup_cost_php'] as $field) {
            if ($values[$field] !== null && abs($values[$field] * 100 - round($values[$field] * 100)) > .0001) { throw new InvalidArgumentException('PHP money inputs must use at most two decimal places.'); }
        }
        foreach (['capacity_per_week', 'expected_monthly_customers'] as $field) {
            if ($values[$field] !== null && (floor($values[$field]) !== $values[$field] || $values[$field] > 10000000)) { throw new InvalidArgumentException('Service/customer counts must be whole numbers up to 10 million.'); }
        }
        if ($values['weekly_hours'] !== null && $values['weekly_hours'] > 168) { throw new InvalidArgumentException('Weekly hours cannot exceed 168.'); }
        $margin = $values['price_per_visit_php'] !== null && $values['variable_cost_php'] !== null ? $values['price_per_visit_php'] - $values['variable_cost_php'] : null;
        $marginCents = $margin === null ? null : (int) round($values['price_per_visit_php'] * 100) - (int) round($values['variable_cost_php'] * 100);
        $fixedCents = $values['fixed_monthly_cost_php'] === null ? null : (int) round($values['fixed_monthly_cost_php'] * 100);
        $breakEven = $marginCents !== null && $marginCents > 0 && $fixedCents !== null ? intdiv($fixedCents + $marginCents - 1, $marginCents) : null;
        $population = null; $cells = []; $existing = $input['existing_coverage_ids'] ?? [];
        if (!is_array($existing) || !is_array($input['coverage_cells'] ?? [])) { throw new InvalidArgumentException('Coverage inputs must be lists.'); }
        $existing = array_map(static fn ($id): string => 'unit:' . self::text($id, 100), $existing);
        if (count($input['coverage_cells'] ?? []) > 1000 || count($existing) > 1000) { throw new InvalidArgumentException('Use at most 1,000 documented coverage units.'); }
        foreach (($input['coverage_cells'] ?? []) as $cell) {
            $cell = self::object($cell);
            $id = 'unit:' . self::text($cell['id'] ?? '', 100); $count = self::number($cell['population'] ?? null, 0, 1000000000);
            $evidence = self::evidence($cell, true);
            if ($count === null || floor($count) !== $count) { throw new InvalidArgumentException('Population cells require a whole-person count and a source.'); }
            if (isset($cells[$id]) && $cells[$id]['population'] !== $count) { throw new InvalidArgumentException('Conflicting populations for the same coverage unit.'); }
            $cells[$id] = ['population' => $count, 'evidence' => $evidence];
        }
        if ($cells !== []) { $population = 0; foreach ($cells as $id => $cell) { if (!in_array($id, $existing, true)) { $population += $cell['population']; } } }
        $net = $margin !== null && $values['fixed_monthly_cost_php'] !== null && $values['expected_monthly_customers'] !== null ? round($margin * $values['expected_monthly_customers'] - $values['fixed_monthly_cost_php'], 2) : null;
        $deltas = []; if ($baseline !== null) { foreach ($fields as $field) { $old = $baseline['inputs'][$field] ?? null; $deltas[$field] = $old !== null && $values[$field] !== null ? $values[$field] - $old : null; } }
        return ['inputs' => $values, 'contribution_per_visit_php' => $margin, 'break_even_visits_per_month' => $breakEven, 'estimated_additional_population_reach' => $population, 'unique_coverage_units' => count($cells), 'assumed_monthly_net_php' => $net, 'deltas_from_baseline' => $deltas, 'unresolved_inputs' => array_keys(array_filter($values, static fn ($value): bool => $value === null)), 'quote_evidence' => self::evidence(self::object($input['quote_evidence'] ?? []), false), 'assumptions' => self::text($input['assumptions'] ?? '', 4000, false), 'notice' => 'Population reach is not expected customers. Coverage uses supplied disjoint unit IDs and excludes recorded existing coverage; overlapping geometry is not inferred. Financial outputs use supplied assumptions only.'];
    }

    public static function distance(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $a = sin(deg2rad($lat2 - $lat1) / 2) ** 2 + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin(deg2rad($lng2 - $lng1) / 2) ** 2;
        return 6371000 * 2 * asin(sqrt(min(1, $a)));
    }

    public static function radar(array $proposal, array $services): array
    {
        $lat = self::number($proposal['lat'] ?? null, -90, 90); $lng = self::number($proposal['lng'] ?? null, -180, 180); $results = [];
        $complements = $proposal['complementary_categories'] ?? [];
        if (!is_array($complements) || array_filter($complements, static fn ($value): bool => !is_string($value)) || array_diff($complements, self::CATEGORIES)) { throw new InvalidArgumentException('Choose supported complementary categories.'); }
        foreach ($services as $service) {
            $payload = $service['payload'] ?? []; $category = $service['category'] ?? '';
            $relation = ($payload['operator_kind'] ?? '') !== 'private' && ($payload['is_free'] ?? false) ? 'public_alternative' : ($category === ($proposal['category'] ?? '') ? 'same_service_category' : (in_array($category, $complements, true) ? 'complementary' : 'other_recorded_service'));
            $distance = $lat !== null && $lng !== null && isset($service['lat'], $service['lng']) ? self::distance($lat, $lng, (float) $service['lat'], (float) $service['lng']) : null;
            if ($distance !== null && $distance > 10000) { continue; }
            $results[] = ['id' => $service['id'], 'title' => $service['title'], 'category' => $category, 'relation' => $relation, 'straight_line_m' => $distance === null ? null : round($distance), 'route_distance_m' => null, 'travel_minutes' => null, 'potential_partner' => (bool) ($payload['open_to_partnership'] ?? false), 'reason' => 'Based on the recorded service category and operator information; no sales or demand inference.', 'last_verified_on' => $payload['verified_on'] ?? null, 'health_facility' => in_array($category, ['healthcare', 'rehabilitation', 'preventive_care'], true), 'walking_wheeling_conditions' => $payload['accessibility'] ?? '', 'evidence' => $payload['evidence'] ?? [], 'unmet_requirements' => array_values(array_filter([$distance === null ? 'Coordinates needed to calculate straight-line proximity' : null, empty($payload['verified_on']) ? 'Current operator verification is not recorded' : null, empty($payload['accessibility']) ? 'Walking, wheeling and entrance conditions need site evidence' : null, 'Route distance and travel time have not been verified']))];
        }
        usort($results, static fn (array $a, array $b): int => ($a['straight_line_m'] ?? INF) <=> ($b['straight_line_m'] ?? INF));
        return ['services' => $results, 'notice' => 'Known coordinate points are included within 10 km straight-line; records with unknown distance remain visible. Distances are not routes or travel times. An absent directory record means missing information, not absence of a service. Walking and wheeling conditions require site evidence.'];
    }

    public static function hash(array $value): string
    {
        $sort = static function (array &$items) use (&$sort): void { if (!self::isList($items)) { ksort($items); } foreach ($items as &$item) { if (is_array($item)) { $sort($item); } } };
        $sort($value);
        return hash('sha256', json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRESERVE_ZERO_FRACTION | JSON_THROW_ON_ERROR));
    }
}
