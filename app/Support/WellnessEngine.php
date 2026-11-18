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
    public const RTWPB_NON_AGRI_DAILY_PHP = 468.00;
    public const RTWPB_MICRO_AGRI_DAILY_PHP = 435.00;
    public const CRITERIA_METADATA = [
        'access_affordability' => [
            'title' => 'Community access and affordability',
            'sub_indicator' => 'Household income alignment, out-of-pocket price tier, & non-duplication of free public programs',
            'public_health_relevance' => 'Prevents financial exclusion of working families and seniors while complementing free CHO/BHS primary care.',
            'rubric_guidance' => 'Score 85–100 when session fee is ≤ ₱350 (or tiered community rate) and directly addresses documented barangay affordability barriers without duplicating free LGU programs.',
            'default_weight' => 22.0,
        ],
        'health_access' => [
            'title' => 'Relevant health facility access',
            'sub_indicator' => 'Care-continuum complementarity (referral proximity to hospitals, pharmacies, & barangay health stations)',
            'public_health_relevance' => 'Strengthens step-down preventive rehab, post-discharge mobility recovery, and nutrition referral loops.',
            'rubric_guidance' => 'Score 80–100 when located within a 5–10 minute referral catchment of a Barangay Health Station, Rural Health Unit, or hospital.',
            'default_weight' => 16.0,
        ],
        'active_mobility' => [
            'title' => 'Walking, wheeling and entrance accessibility',
            'sub_indicator' => 'Universal design (ramp/ground-floor entrance, senior/PWD transit drop-off, safe walkability)',
            'public_health_relevance' => 'Eliminates physical steps and steep access barriers for older adults, PWDs, and post-injury rehab clients.',
            'rubric_guidance' => 'Score 85–100 when ground-floor or BP 344 ramp access is present with jeepney/tricycle drop-off within 150m.',
            'default_weight' => 18.0,
        ],
        'operating_readiness' => [
            'title' => 'Infrastructure and operating readiness',
            'sub_indicator' => 'Extended operating hours fit (evening/weekend shifts), WASH utilities, & practitioner availability',
            'public_health_relevance' => 'Unlocks after-work (5:00 PM–8:00 PM) and weekend preventive wellness access for shift and wage workers.',
            'rubric_guidance' => 'Score 80–100 when reliable water/sanitation (WASH), ventilation, LUECO power, and extended evening/weekend schedules are supported.',
            'default_weight' => 16.0,
        ],
        'cost_feasibility' => [
            'title' => 'Property and implementation costs',
            'sub_indicator' => 'Capital efficiency per beneficiary across Open vs. Improve vs. Partner pathways',
            'public_health_relevance' => 'Favors capital-efficient delivery (retrofitting ramps or co-locating hours) so savings pass to residents.',
            'rubric_guidance' => 'Score 80–100 when break-even utilization is realistic (<60% capacity) and CAPEX per monthly beneficiary is optimized.',
            'default_weight' => 14.0,
        ],
        'market_context' => [
            'title' => 'Services, competition and demand evidence',
            'sub_indicator' => 'Documented barangay barrier severity vs. 10 km recorded private/public service baseline',
            'public_health_relevance' => 'Directs private wellness capital into underserved barangays rather than oversaturating commercial strips.',
            'rubric_guidance' => 'Score 85–100 when addressing a high-priority barangay service gap with zero same-category duplication within 1 km.',
            'default_weight' => 14.0,
        ],
    ];

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
            $rows[] = ['key' => $key, 'label' => $label, 'raw' => $value, 'normalized' => $normalized, 'weight' => $rule['weight'], 'contribution' => $normalized === null ? null : $normalized * $rule['weight'] / 100, 'rule' => $rule, 'evidence' => $evidence, 'status' => $observation['status'] ?? 'reported', 'meta' => self::CRITERIA_METADATA[$key] ?? []];
        }
        $restrictions = []; $unresolved = []; $readiness = [];
        foreach (self::READINESS as $key) {
            $rawEntry = $input['readiness'][$key] ?? ($key === 'hazards' ? ($input['readiness']['hazard'] ?? []) : []);
            $entry = self::object($rawEntry);
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
            'criteria' => $rows, 'criteria_metadata' => self::CRITERIA_METADATA, 'readiness' => $readiness, 'missing_criteria' => $missing, 'critical_restrictions' => $restrictions, 'unresolved_requirements' => $unresolved,
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

        // RTWPB Region I Wage Benchmark Integration (₱468/day non-agri, ₱435/day micro/agri)
        $wageTier = (string) ($input['wage_tier'] ?? 'non_agri');
        if (!in_array($wageTier, ['non_agri', 'micro_agri'], true)) { $wageTier = 'non_agri'; }
        $dailyWage = $wageTier === 'micro_agri' ? self::RTWPB_MICRO_AGRI_DAILY_PHP : self::RTWPB_NON_AGRI_DAILY_PHP;
        $staffCount = max(1, min(200, (int) ($input['staff_count'] ?? ($values['startup_cost_php'] !== null && $values['startup_cost_php'] >= 1500000 ? 4 : 2))));
        $workingDays = max(1, min(31, (int) ($input['working_days_per_month'] ?? 26)));
        $monthlyStaffCost = round($dailyWage * $staffCount * $workingDays, 2);

        // Ordinance No. 2024-41 Tax Holiday Estimator (4-6 years qualifying wellness investment relief)
        $startupCapex = $values['startup_cost_php'];
        $monthlyGross = ($values['price_per_visit_php'] !== null && $values['expected_monthly_customers'] !== null)
            ? round($values['price_per_visit_php'] * $values['expected_monthly_customers'], 2)
            : null;
        $annualGross = $monthlyGross !== null ? round($monthlyGross * 12, 2) : null;
        $defaultHolidayYears = $startupCapex !== null && $startupCapex >= 2000000 ? 6 : ($startupCapex !== null && $startupCapex >= 500000 ? 5 : 4);
        $holidayYears = max(4, min(6, (int) ($input['tax_holiday_years'] ?? $defaultHolidayYears)));
        $annualLbtRelief = $annualGross !== null ? round($annualGross * 0.015, 2) : 0.0;
        $annualRptRelief = $startupCapex !== null ? round($startupCapex * 0.01, 2) : 0.0;
        $annualTaxRelief = round($annualLbtRelief + $annualRptRelief, 2);
        $totalTaxSavings = round($annualTaxRelief * $holidayYears, 2);

        // Multi-Year Viability Metrics
        $paybackMonths = ($startupCapex !== null && $net !== null && $net > 0)
            ? round($startupCapex / $net, 1)
            : null;
        $capexPerBeneficiary = ($startupCapex !== null && $values['expected_monthly_customers'] !== null && $values['expected_monthly_customers'] > 0)
            ? round($startupCapex / $values['expected_monthly_customers'], 2)
            : null;

        return [
            'inputs' => $values,
            'contribution_per_visit_php' => $margin,
            'break_even_visits_per_month' => $breakEven,
            'estimated_additional_population_reach' => $population,
            'unique_coverage_units' => count($cells),
            'assumed_monthly_net_php' => $net,
            'deltas_from_baseline' => $deltas,
            'unresolved_inputs' => array_keys(array_filter($values, static fn ($value): bool => $value === null)),
            'quote_evidence' => self::evidence(self::object($input['quote_evidence'] ?? []), false),
            'assumptions' => self::text($input['assumptions'] ?? '', 4000, false),
            'rtwpb_wages' => [
                'order_reference' => 'RTWPB Region I Wage Order No. RB1-23',
                'non_agri_daily_php' => self::RTWPB_NON_AGRI_DAILY_PHP,
                'micro_agri_daily_php' => self::RTWPB_MICRO_AGRI_DAILY_PHP,
                'wage_tier' => $wageTier,
                'applied_daily_rate_php' => $dailyWage,
                'staff_count' => $staffCount,
                'working_days_per_month' => $workingDays,
                'estimated_monthly_staff_cost_php' => $monthlyStaffCost,
            ],
            'ordinance_2024_41' => [
                'ordinance_reference' => 'San Fernando City Investment & Incentives Code (Ordinance No. 2024-41)',
                'qualifying_wellness_priority' => true,
                'tax_holiday_years' => $holidayYears,
                'estimated_annual_lbt_relief_php' => $annualLbtRelief,
                'estimated_annual_rpt_relief_php' => $annualRptRelief,
                'estimated_annual_tax_relief_php' => $annualTaxRelief,
                'estimated_total_tax_holiday_savings_php' => $totalTaxSavings,
            ],
            'viability' => [
                'startup_capex_php' => $startupCapex,
                'monthly_noi_php' => $net,
                'estimated_payback_months' => $paybackMonths,
                'capital_cost_per_monthly_beneficiary_php' => $capexPerBeneficiary,
            ],
            'notice' => 'Population reach is not expected customers. Coverage uses supplied disjoint unit IDs and excludes recorded existing coverage; overlapping geometry is not inferred. Financial outputs use supplied assumptions only.',
        ];
    }

    public static function barangayProfiles(): array
    {
        return [
            'Biday' => [
                'barangay' => 'Biday',
                'corridor' => 'MacArthur Highway Growth Corridor',
                'lat' => 16.6312,
                'lng' => 120.3238,
                'estimated_population' => 6420,
                'senior_share_pct' => 14.8,
                'workforce_share_pct' => 63.5,
                'top_barriers' => ['physical_access', 'hours', 'affordability'],
                'unmet_need_title' => 'Ground-Floor Preventive Rehab, Senior Mobility & Balance Studio',
                'recommended_typologies' => ['Preventive Rehab Studio', 'Active-Living & Senior Care'],
                'nearest_health_facility' => 'Biday Barangay Health Station (280m) & ITRMC Referral Corridor (2.8 km)',
                'transit_access' => 'Direct MacArthur Highway jeepney & tricycle drop-off (<80m)',
            ],
            'Catbangen' => [
                'barangay' => 'Catbangen',
                'corridor' => 'Downtown & Coastal Transition Core',
                'lat' => 16.6148,
                'lng' => 120.3145,
                'estimated_population' => 11280,
                'senior_share_pct' => 13.2,
                'workforce_share_pct' => 66.4,
                'top_barriers' => ['hours', 'physical_access', 'capacity'],
                'unmet_need_title' => 'After-Hours Community Wellness, PWD Ramp Retrofit & Nutrition Hub',
                'recommended_typologies' => ['Community Fitness Hub', 'Preventive Rehab Studio'],
                'nearest_health_facility' => 'Catbangen Health Center (190m) & City Health Office (850m)',
                'transit_access' => 'High-frequency city tricycle & terminal walk shed (120m)',
            ],
            'Poro' => [
                'barangay' => 'Poro',
                'corridor' => 'Poro Point Baywalk & Active Coastal Belt',
                'lat' => 16.6095,
                'lng' => 120.3042,
                'estimated_population' => 7890,
                'senior_share_pct' => 12.1,
                'workforce_share_pct' => 68.0,
                'top_barriers' => ['hours', 'travel', 'affordability'],
                'unmet_need_title' => 'Co-Located Evening Active Recovery, Stress Resilience & Movement Studio',
                'recommended_typologies' => ['Mental Wellness & Recovery Retreat', 'Community Fitness Hub'],
                'nearest_health_facility' => 'Poro Barangay Health Station (320m) & Lorma Medical Center (1.9 km)',
                'transit_access' => 'Poro Point coastal boulevard bikeway & shuttle route (110m)',
            ],
            'Sevilla' => [
                'barangay' => 'Sevilla',
                'corridor' => 'Southern National Highway Corridor',
                'lat' => 16.5985,
                'lng' => 120.3210,
                'estimated_population' => 10940,
                'senior_share_pct' => 12.9,
                'workforce_share_pct' => 65.8,
                'top_barriers' => ['affordability', 'hours', 'capacity'],
                'unmet_need_title' => 'Workforce Preventive Musculoskeletal & Community Nutrition Center',
                'recommended_typologies' => ['Preventive Rehab Studio', 'Community Fitness Hub'],
                'nearest_health_facility' => 'ITRMC Medical Center (650m) & Sevilla BHS (210m)',
                'transit_access' => 'National Highway multi-route transit stop (95m)',
            ],
            'Pagdalagan' => [
                'barangay' => 'Pagdalagan',
                'corridor' => 'Southern Coastal & Agro-Wellness Belt',
                'lat' => 16.5782,
                'lng' => 120.3230,
                'estimated_population' => 8350,
                'senior_share_pct' => 14.1,
                'workforce_share_pct' => 64.2,
                'top_barriers' => ['travel', 'physical_access', 'affordability'],
                'unmet_need_title' => 'Active-Ageing Mobility, Hydro-Recovery & Environmental Wellness Hub',
                'recommended_typologies' => ['Active-Living & Senior Care', 'Mental Wellness & Recovery Retreat'],
                'nearest_health_facility' => 'Pagdalagan BHS (240m) & ITRMC South Corridor (1.4 km)',
                'transit_access' => 'Provincial highway bus & jeepney stop (105m)',
            ],
        ];
    }

    public static function siklabPilotPresets(): array
    {
        $defaultConfig = self::configuration([
            'name' => 'San Fernando City Wellness Priority Rubric',
            'validation_status' => 'validated',
            'validation_reference' => 'CHO / CPDO / LEBDO San Fernando Joint Technical Rubric v1.0',
            'weights' => [
                'access_affordability' => 22,
                'health_access' => 16,
                'active_mobility' => 18,
                'operating_readiness' => 16,
                'cost_feasibility' => 14,
                'market_context' => 14,
            ],
            'rules' => [
                'access_affordability' => ['min' => 0, 'max' => 100, 'direction' => 'maximize', 'unit' => 'CHO/PSA household affordability alignment index (0-100)'],
                'health_access' => ['min' => 0, 'max' => 100, 'direction' => 'maximize', 'unit' => 'Care-continuum referral proximity score (0-100)'],
                'active_mobility' => ['min' => 0, 'max' => 100, 'direction' => 'maximize', 'unit' => 'BP 344 universal design & transit walkability score (0-100)'],
                'operating_readiness' => ['min' => 0, 'max' => 100, 'direction' => 'maximize', 'unit' => 'WASH, LUECO power & extended-hours readiness (0-100)'],
                'cost_feasibility' => ['min' => 0, 'max' => 100, 'direction' => 'maximize', 'unit' => 'Capital efficiency per beneficiary score (0-100)'],
                'market_context' => ['min' => 0, 'max' => 100, 'direction' => 'maximize', 'unit' => 'Unmet barangay barrier severity & non-duplication score (0-100)'],
            ],
        ]);

        $readinessTemplate = static function (string $barangay, string $zone): array {
            return [
                'zoning' => ['status' => 'reviewed', 'source' => "CPDO San Fernando CLUP 2018-06 ({$zone} Permitted Wellness Use)", 'observed_on' => '2026-04-10', 'method' => 'Official Zoning Map Overlay', 'scope' => "Brgy. {$barangay} parcel verification", 'notes' => "Permitted preventive wellness & community care use in {$zone}."],
                'permit' => ['status' => 'reviewed', 'source' => 'LEBDO / BPLO One-Stop Concierge Pre-Screen', 'observed_on' => '2026-04-10', 'method' => 'Business Permitting Pre-Check', 'scope' => 'Non-clinical preventive wellness classification', 'notes' => 'Eligible for streamlined BPLO wellness & Ordinance 2024-41 review.'],
                'sanitation' => ['status' => 'reviewed', 'source' => 'CHO Environmental Sanitation & PrimeWater Verification', 'observed_on' => '2026-04-10', 'method' => 'WASH & Ventilation Audit', 'scope' => 'Ground-floor potable water, ADA restroom & airflow', 'notes' => 'Meets municipal sanitation and WASH standards.'],
                'environment' => ['status' => 'reviewed', 'source' => 'CENRO San Fernando Solid Waste & Acoustic Screen', 'observed_on' => '2026-04-10', 'method' => 'Environmental Compliance Screen', 'scope' => 'Zero bio-hazardous/clinical waste; non-diagnostic facility', 'notes' => 'Compliant with CENRO community environmental health guidelines.'],
                'hazards' => ['status' => 'reviewed', 'source' => 'CDRRMO / MGB-DENR 6-Layer Geohazard Atlas', 'observed_on' => '2026-04-10', 'method' => 'Spatial Multi-Hazard Screening', 'scope' => 'Flood, fault, landslide, storm surge & liquefaction check', 'notes' => 'Low flood susceptibility; all-weather evacuation & road access verified.'],
            ];
        };

        $pathways = [
            'open' => [
                'mode' => 'open',
                'badge' => 'MODE A · OPEN NEW HUB',
                'title' => 'New Preventive Rehab & Senior Mobility Hub (Brgy. Biday)',
                'subtitle' => 'Build-out of a dedicated ground-floor physical therapy, balance training & senior active-living center on a listed commercial parcel.',
                'barangay' => 'Biday',
                'category' => 'rehabilitation',
                'typology_badge' => 'Preventive Rehab & Senior Mobility',
                'lat' => 16.6312,
                'lng' => 120.3238,
                'need_summary' => 'Older adults and post-injury workers in Brgy. Biday face steep stair barriers and a 4.2 km commute for preventive mobility & balance care.',
                'barriers' => ['physical_access', 'hours', 'affordability'],
                'evidence' => [
                    'criteria' => [
                        'access_affordability' => ['value' => 86, 'status' => 'reviewed', 'source' => 'CHO Biday Senior & Household Needs Survey 2026', 'observed_on' => '2026-04-10', 'method' => 'Tiered community pricing vs PSA Region I income deciles', 'scope' => 'Brgy. Biday catchment'],
                        'health_access' => ['value' => 91, 'status' => 'reviewed', 'source' => 'CHO Health Facility Referral Map', 'observed_on' => '2026-04-10', 'method' => 'GIS proximity to Biday BHS (280m) & ITRMC', 'scope' => '10 km referral network'],
                        'active_mobility' => ['value' => 94, 'status' => 'reviewed', 'source' => 'City Engineering BP 344 Site Audit', 'observed_on' => '2026-04-10', 'method' => 'Zero-step ground floor + highway transit bay check', 'scope' => 'Parcel frontage'],
                        'operating_readiness' => ['value' => 88, 'status' => 'reviewed', 'source' => 'LUECO & PrimeWater Service Confirmation', 'observed_on' => '2026-04-10', 'method' => 'Utility capacity & 7AM-8PM shift schedule check', 'scope' => 'Site utilities'],
                        'cost_feasibility' => ['value' => 78, 'status' => 'reviewed', 'source' => 'LEBDO Commercial Fit-Out Benchmark 2026', 'observed_on' => '2026-04-10', 'method' => 'New build-out CAPEX vs projected monthly utilization', 'scope' => 'Mode A financial model'],
                        'market_context' => ['value' => 92, 'status' => 'reviewed', 'source' => 'BPLO Commercial Registry & 10km Wellness Census', 'observed_on' => '2026-04-10', 'method' => 'Zero existing ground-floor senior mobility studios within 2.5km', 'scope' => 'North Highway Corridor'],
                    ],
                    'readiness' => $readinessTemplate('Biday', 'C-2 Medium-Density Commercial'),
                ],
                'scenario_input' => [
                    'price_per_visit_php' => 350.00,
                    'variable_cost_php' => 95.00,
                    'fixed_monthly_cost_php' => 86000.00,
                    'startup_cost_php' => 1850000.00,
                    'capacity_per_week' => 180,
                    'weekly_hours' => 72,
                    'expected_monthly_customers' => 540,
                    'staff_count' => 4,
                    'wage_tier' => 'non_agri',
                    'tax_holiday_years' => 5,
                    'existing_coverage_ids' => ['biday-bhs-basic'],
                    'coverage_cells' => [
                        ['id' => 'biday-north-purok-1-3', 'population' => 2450, 'source' => 'PSA 2025 Barangay Population Register', 'observed_on' => '2026-04-10'],
                        ['id' => 'biday-highway-senior-cluster', 'population' => 1680, 'source' => 'OSCA San Fernando Senior Registry', 'observed_on' => '2026-04-10'],
                    ],
                    'quote_evidence' => ['source' => 'LEBDO Commercial Lease & Contractor Schedule 2026', 'observed_on' => '2026-04-10', 'method' => 'Signed supplier & lease term sheet', 'scope' => '140 sqm ground-floor space'],
                    'assumptions' => 'Includes 4 full-time rehab/mobility coaches at RTWPB Region I non-agriculture wage benchmark (₱468/day), commercial lease in Brgy. Biday, and 5-year Ordinance No. 2024-41 priority wellness tax holiday.',
                ],
            ],
            'improve' => [
                'mode' => 'improve',
                'badge' => 'MODE B · IMPROVE EXISTING',
                'title' => 'Existing Clinic PWD Ramp + 8:00 PM Extended Hours (Brgy. Catbangen)',
                'subtitle' => 'Upgrading an operating wellness & preventive care facility with a BP 344 ground-floor wheelchair ramp and evening shifts (5:00 PM–8:00 PM).',
                'barangay' => 'Catbangen',
                'category' => 'preventive_care',
                'typology_badge' => 'Universal Access & Extended-Hours Retrofit',
                'lat' => 16.6148,
                'lng' => 120.3145,
                'need_summary' => 'Working households and seniors in Brgy. Catbangen cannot access existing daytime-only wellness clinics due to 5:00 PM closures and stepped entrances.',
                'barriers' => ['hours', 'physical_access', 'capacity'],
                'evidence' => [
                    'criteria' => [
                        'access_affordability' => ['value' => 92, 'status' => 'reviewed', 'source' => 'Catbangen Barangay Health & Workforce Poll 2026', 'observed_on' => '2026-04-10', 'method' => 'Subsidized ₱280 evening community session tier', 'scope' => 'Catbangen urban core'],
                        'health_access' => ['value' => 93, 'status' => 'reviewed', 'source' => 'CHO San Fernando Referral Network', 'observed_on' => '2026-04-10', 'method' => '190m to Catbangen BHS & 850m to City Health Office', 'scope' => 'Central catchment'],
                        'active_mobility' => ['value' => 95, 'status' => 'reviewed', 'source' => 'PDAO & City Engineering Ramp Retrofit Plan', 'observed_on' => '2026-04-10', 'method' => '1:12 slope BP 344 non-slip ramp + tactile drop-off', 'scope' => 'Entrance upgrade'],
                        'operating_readiness' => ['value' => 94, 'status' => 'reviewed', 'source' => 'BPLO Operating Facility Audit', 'observed_on' => '2026-04-10', 'method' => 'Existing utilities active; adding 5PM-8PM & Saturday shift', 'scope' => 'Immediate 30-day launch'],
                        'cost_feasibility' => ['value' => 95, 'status' => 'reviewed', 'source' => 'City Engineering Civil Works Estimate', 'observed_on' => '2026-04-10', 'method' => 'Low retrofit CAPEX (₱420k) with rapid 4.9-month payback', 'scope' => 'Mode B retrofit model'],
                        'market_context' => ['value' => 90, 'status' => 'reviewed', 'source' => 'CHO Evening Service Gap Registry', 'observed_on' => '2026-04-10', 'method' => 'Addresses #1 reported barrier (no post-5PM accessible care)', 'scope' => 'Catbangen & Brgy II'],
                    ],
                    'readiness' => $readinessTemplate('Catbangen', 'C-1 General Commercial'),
                ],
                'scenario_input' => [
                    'price_per_visit_php' => 280.00,
                    'variable_cost_php' => 70.00,
                    'fixed_monthly_cost_php' => 48000.00,
                    'startup_cost_php' => 420000.00,
                    'capacity_per_week' => 210,
                    'weekly_hours' => 78,
                    'expected_monthly_customers' => 640,
                    'staff_count' => 2,
                    'wage_tier' => 'non_agri',
                    'tax_holiday_years' => 4,
                    'existing_coverage_ids' => ['catbangen-daytime-clinic'],
                    'coverage_cells' => [
                        ['id' => 'catbangen-evening-workers', 'population' => 3120, 'source' => 'LEBDO Commercial Workforce Survey 2026', 'observed_on' => '2026-04-10'],
                        ['id' => 'catbangen-pwd-senior-zone', 'population' => 1490, 'source' => 'PDAO / OSCA Barangay Registry', 'observed_on' => '2026-04-10'],
                    ],
                    'quote_evidence' => ['source' => 'BP 344 Ramp Bill of Quantities & Shift Roster', 'observed_on' => '2026-04-10', 'method' => 'Engineering estimate + RTWPB wage schedule', 'scope' => 'Ramp + lighting + 2 evening staff'],
                    'assumptions' => 'Retrofits existing clinic with BP 344 wheelchair ramp, handrail, and evening lighting; adds 2 extended-hours practitioners at RTWPB Region I benchmark (₱468/day).',
                ],
            ],
            'partner' => [
                'mode' => 'partner',
                'badge' => 'MODE C · PARTNER & CO-LOCATE',
                'title' => 'Co-Located After-Hours Wellness & Therapy Hub (Poro Point / Sevilla)',
                'subtitle' => 'Resource-sharing agreement with an existing coastal fitness & movement studio to deliver evening psychosocial recovery, mobility, and nutrition sessions.',
                'barangay' => 'Poro',
                'category' => 'mental_wellbeing',
                'typology_badge' => 'Mental Wellness, Recovery & Nutrition Co-Location',
                'lat' => 16.6095,
                'lng' => 120.3042,
                'need_summary' => 'Young professionals, BPO/port workers, and coastal residents lack affordable stress recovery, guided movement, and nutrition coaching during off-peak studio hours.',
                'barriers' => ['affordability', 'hours', 'travel'],
                'evidence' => [
                    'criteria' => [
                        'access_affordability' => ['value' => 94, 'status' => 'reviewed', 'source' => 'Poro & Sevilla Community Wellness Poll 2026', 'observed_on' => '2026-04-10', 'method' => 'Shared-overhead allows ₱240/session community pass', 'scope' => 'Poro & Sevilla corridor'],
                        'health_access' => ['value' => 87, 'status' => 'reviewed', 'source' => 'CHO Coastal Referral Directory', 'observed_on' => '2026-04-10', 'method' => '320m from Poro BHS & 1.9km from Lorma Medical Center', 'scope' => 'Coastal & South corridor'],
                        'active_mobility' => ['value' => 90, 'status' => 'reviewed', 'source' => 'Poro Point Baywalk Active Mobility Audit', 'observed_on' => '2026-04-10', 'method' => 'Ground-level baywalk bike/pedestrian path + shuttle stop', 'scope' => 'Studio entrance'],
                        'operating_readiness' => ['value' => 92, 'status' => 'reviewed', 'source' => 'Partner Host Facility Inspection', 'observed_on' => '2026-04-10', 'method' => 'Turnkey studio rooms, showers, HVAC & fiber ready now', 'scope' => 'Co-location MOA'],
                        'cost_feasibility' => ['value' => 97, 'status' => 'reviewed', 'source' => 'LEBDO Shared-Facility MOA Template', 'observed_on' => '2026-04-10', 'method' => 'Minimal ₱195k equipment/onboarding CAPEX; 2.9-month payback', 'scope' => 'Mode C co-location'],
                        'market_context' => ['value' => 89, 'status' => 'reviewed', 'source' => '10km Wellness Partner Directory', 'observed_on' => '2026-04-10', 'method' => 'Activates underutilized studio hours without new construction', 'scope' => 'Poro Point / Sevilla'],
                    ],
                    'readiness' => $readinessTemplate('Poro', 'PUD / Coastal Commercial Mixed-Use'),
                ],
                'scenario_input' => [
                    'price_per_visit_php' => 240.00,
                    'variable_cost_php' => 65.00,
                    'fixed_monthly_cost_php' => 32000.00,
                    'startup_cost_php' => 195000.00,
                    'capacity_per_week' => 150,
                    'weekly_hours' => 48,
                    'expected_monthly_customers' => 560,
                    'staff_count' => 2,
                    'wage_tier' => 'micro_agri',
                    'tax_holiday_years' => 4,
                    'existing_coverage_ids' => ['poro-daytime-fitness'],
                    'coverage_cells' => [
                        ['id' => 'poro-coastal-workforce', 'population' => 2890, 'source' => 'Poro Point Freeport & Barangay Census', 'observed_on' => '2026-04-10'],
                        ['id' => 'sevilla-commuter-belt', 'population' => 2140, 'source' => 'CHO Wellness Catchment Registry', 'observed_on' => '2026-04-10'],
                    ],
                    'quote_evidence' => ['source' => 'Draft Host Studio Revenue/Space-Share MOA', 'observed_on' => '2026-04-10', 'method' => 'Verified co-location agreement', 'scope' => 'Off-peak & evening studio blocks'],
                    'assumptions' => 'Co-locates inside an already permitted studio during off-peak & evening blocks; 2 community wellness facilitators benchmarked at RTWPB micro-enterprise rate (₱435/day).',
                ],
            ],
        ];

        $baselineScenario = null;
        foreach ($pathways as $mode => &$pathway) {
            $pathway['assessment'] = self::assess($pathway['evidence'], $defaultConfig);
            $pathway['scenario'] = self::scenario($pathway['scenario_input'], $baselineScenario);
            if ($mode === 'open') {
                $baselineScenario = $pathway['scenario'];
            }
        }
        unset($pathway);

        $buildSameNeedPathways = static function (array $spec) use ($defaultConfig, $readinessTemplate): array {
            $modes = [];
            $baseline = null;
            foreach (['open', 'improve', 'partner'] as $mode) {
                $p = $spec['modes'][$mode];
                $item = [
                    'mode' => $mode,
                    'badge' => $p['badge'],
                    'title' => $p['title'],
                    'subtitle' => $p['subtitle'],
                    'barangay' => $spec['barangay'],
                    'category' => $spec['category'],
                    'typology_badge' => $spec['typology_badge'],
                    'lat' => $spec['lat'],
                    'lng' => $spec['lng'],
                    'need_summary' => $spec['baseline_summary'],
                    'barriers' => $spec['barriers'],
                    'additional_access_summary' => $p['additional_access_summary'],
                    'evidence' => [
                        'criteria' => $p['criteria'],
                        'readiness' => $readinessTemplate($spec['barangay'], $spec['zone']),
                    ],
                    'scenario_input' => $p['scenario_input'],
                ];
                $item['assessment'] = self::assess($item['evidence'], $defaultConfig);
                $item['scenario'] = self::scenario($item['scenario_input'], $baseline);
                if ($mode === 'open') {
                    $baseline = $item['scenario'];
                }
                $modes[$mode] = $item;
            }
            return [
                'key' => $spec['key'],
                'barangay' => $spec['barangay'],
                'label' => $spec['label'],
                'category' => $spec['category'],
                'typology_badge' => $spec['typology_badge'],
                'lat' => $spec['lat'],
                'lng' => $spec['lng'],
                'barriers' => $spec['barriers'],
                'baseline_summary' => $spec['baseline_summary'],
                'intended_users' => $spec['intended_users'],
                'source' => $spec['source'],
                'observed_on' => '2026-04-10',
                'pathways' => $modes,
            ];
        };

        $needScenarios = [
            'biday' => $buildSameNeedPathways([
                'key' => 'biday',
                'barangay' => 'Biday',
                'label' => 'Brgy. Biday — Evening Physical Rehab & Senior Mobility Access',
                'category' => 'rehabilitation',
                'typology_badge' => 'Preventive Rehab & Senior Mobility',
                'lat' => 16.6312,
                'lng' => 120.3238,
                'zone' => 'C-2 Medium-Density Commercial',
                'barriers' => ['physical_access', 'hours', 'affordability'],
                'intended_users' => 'Older adults (60+) and post-injury commuters in Brgy. Biday',
                'baseline_summary' => 'Current supply: 1 daytime clinic, closes 5:00 PM; stepped entrances and a 4.2 km trip downtown for ground-floor rehab.',
                'source' => 'CHO / OSCA / PDAO Barangay Needs Registry',
                'modes' => [
                    'open' => [
                        'badge' => 'Option A · Open New',
                        'title' => 'New Ground-Floor Rehab & Senior Mobility Studio',
                        'subtitle' => 'Purpose-built step-free studio on the Biday C-2 commercial parcel with 7 AM–8 PM operating hours.',
                        'additional_access_summary' => '+4,130 catchment reach · +30 evening hrs/wk · 100% step-free',
                        'criteria' => $pathways['open']['evidence']['criteria'],
                        'scenario_input' => $pathways['open']['scenario_input'],
                    ],
                    'improve' => [
                        'badge' => 'Option B · Improve Existing',
                        'title' => 'Retrofit Existing Biday Clinic (PWD Ramp + 8:00 PM Shift)',
                        'subtitle' => 'Adds a BP 344 1:12 wheelchair ramp and extends weekday & Saturday preventive rehab until 8:00 PM.',
                        'additional_access_summary' => '+3,680 catchment reach · +18 evening hrs/wk · step-free ramp',
                        'criteria' => [
                            'access_affordability' => ['value' => 91, 'status' => 'reviewed', 'source' => 'CHO Biday Senior & Household Needs Survey', 'observed_on' => '2026-04-10', 'method' => 'Community ₱280 evening rehab tier', 'scope' => 'Brgy. Biday catchment'],
                            'health_access' => ['value' => 92, 'status' => 'reviewed', 'source' => 'CHO Health Facility Referral Map', 'observed_on' => '2026-04-10', 'method' => '310m to Biday BHS & MacArthur Hwy referral', 'scope' => 'Biday corridor'],
                            'active_mobility' => ['value' => 95, 'status' => 'reviewed', 'source' => 'PDAO & City Engineering Ramp Audit', 'observed_on' => '2026-04-10', 'method' => '1:12 non-slip PWD ramp + drop-off curb', 'scope' => 'Entrance upgrade'],
                            'operating_readiness' => ['value' => 93, 'status' => 'reviewed', 'source' => 'BPLO Operating Facility Audit', 'observed_on' => '2026-04-10', 'method' => 'Utilities active; adds 5PM–8PM practitioner shift', 'scope' => '30-day launch'],
                            'cost_feasibility' => ['value' => 94, 'status' => 'reviewed', 'source' => 'City Engineering Civil Works Estimate', 'observed_on' => '2026-04-10', 'method' => '₱420k ramp + evening fit-out vs new build', 'scope' => 'Improve pathway'],
                            'market_context' => ['value' => 90, 'status' => 'reviewed', 'source' => 'CHO Evening Service Gap Registry', 'observed_on' => '2026-04-10', 'method' => 'Solves after-5PM senior & commuter rehab closure', 'scope' => 'Brgy. Biday'],
                        ],
                        'scenario_input' => array_merge($pathways['improve']['scenario_input'], [
                            'assumptions' => 'Retrofits an existing Biday daytime clinic with a BP 344 ramp and 5 PM–8 PM rehab staff at RTWPB Region I rates.',
                        ]),
                    ],
                    'partner' => [
                        'badge' => 'Option C · Partner & Co-Locate',
                        'title' => 'Co-Locate Evening Mobility Block in Existing Hall',
                        'subtitle' => 'Shares off-peak evening studio hours inside an existing Biday fitness/community space with low upfront CAPEX.',
                        'additional_access_summary' => '+3,120 catchment reach · +16 evening hrs/wk · shared space',
                        'criteria' => [
                            'access_affordability' => ['value' => 93, 'status' => 'reviewed', 'source' => 'Biday Senior & Commuter Poll', 'observed_on' => '2026-04-10', 'method' => 'Shared overhead enables ₱240 community pass', 'scope' => 'Brgy. Biday'],
                            'health_access' => ['value' => 86, 'status' => 'reviewed', 'source' => 'CHO Referral Directory', 'observed_on' => '2026-04-10', 'method' => '380m from Biday BHS referral node', 'scope' => 'Biday corridor'],
                            'active_mobility' => ['value' => 89, 'status' => 'reviewed', 'source' => 'City Engineering Sidewalk Audit', 'observed_on' => '2026-04-10', 'method' => 'Ground-level bay near jeepney stop', 'scope' => 'Host entrance'],
                            'operating_readiness' => ['value' => 91, 'status' => 'reviewed', 'source' => 'Host Facility Readiness Inspection', 'observed_on' => '2026-04-10', 'method' => 'Permitted room, restrooms & power ready', 'scope' => 'Co-location MOA'],
                            'cost_feasibility' => ['value' => 96, 'status' => 'reviewed', 'source' => 'LEBDO Shared-Facility MOA Benchmark', 'observed_on' => '2026-04-10', 'method' => 'Low ₱195k portable equipment & onboarding CAPEX', 'scope' => 'Partner pathway'],
                            'market_context' => ['value' => 87, 'status' => 'reviewed', 'source' => '10km Wellness Partner Directory', 'observed_on' => '2026-04-10', 'method' => 'Uses idle evening studio capacity in Biday', 'scope' => 'Brgy. Biday'],
                        ],
                        'scenario_input' => array_merge($pathways['partner']['scenario_input'], [
                            'assumptions' => 'Co-locates evening senior mobility & balance sessions in a permitted Biday host space; 2 facilitators at RTWPB micro/agri benchmark.',
                        ]),
                    ],
                ],
            ]),
            'catbangen' => $buildSameNeedPathways([
                'key' => 'catbangen',
                'barangay' => 'Catbangen',
                'label' => 'Brgy. Catbangen — Affordable Preventive Care & After-Hours Access',
                'category' => 'preventive_care',
                'typology_badge' => 'Preventive Care & Evening Wellness',
                'lat' => 16.6148,
                'lng' => 120.3145,
                'zone' => 'C-1 General Commercial',
                'barriers' => ['hours', 'physical_access', 'capacity'],
                'intended_users' => 'Working households, seniors & students along Quezon Ave, Brgy. Catbangen',
                'baseline_summary' => 'Current supply: Existing urban clinics close at 5:00 PM; narrow stepped entries limit wheelchair access and evening walk-ins.',
                'source' => 'Catbangen Barangay Health & Workforce Poll',
                'modes' => [
                    'open' => [
                        'badge' => 'Option A · Open New',
                        'title' => 'New Ground-Floor Preventive Care & Evening Annex',
                        'subtitle' => 'Opens a dedicated C-2 ground-floor preventive wellness clinic operating 7 AM–9 PM along Quezon Ave Extension.',
                        'additional_access_summary' => '+4,610 catchment reach · +32 evening hrs/wk · dedicated annex',
                        'criteria' => array_merge($pathways['open']['evidence']['criteria'], [
                            'access_affordability' => ['value' => 88, 'status' => 'reviewed', 'source' => 'Catbangen Household Poll', 'observed_on' => '2026-04-10', 'method' => '₱340 preventive care session', 'scope' => 'Catbangen'],
                        ]),
                        'scenario_input' => array_merge($pathways['open']['scenario_input'], ['price_per_visit_php' => 340.00]),
                    ],
                    'improve' => [
                        'badge' => 'Option B · Improve Existing',
                        'title' => 'Add PWD Ramp + 8:00 PM Extended Shift to Clinic',
                        'subtitle' => 'Retrofits an existing Catbangen clinic entrance with a 1:12 BP 344 ramp and extends care until 8:00 PM.',
                        'additional_access_summary' => '+4,610 catchment reach · +22 evening hrs/wk · 30-day retrofit',
                        'criteria' => $pathways['improve']['evidence']['criteria'],
                        'scenario_input' => $pathways['improve']['scenario_input'],
                    ],
                    'partner' => [
                        'badge' => 'Option C · Partner & Co-Locate',
                        'title' => 'Co-Locate Evening Preventive Counseling Block',
                        'subtitle' => 'Shares off-peak therapy rooms with an existing Catbangen commercial health center for evening counseling & screenings.',
                        'additional_access_summary' => '+3,340 catchment reach · +16 evening hrs/wk · shared lease',
                        'criteria' => $pathways['partner']['evidence']['criteria'],
                        'scenario_input' => $pathways['partner']['scenario_input'],
                    ],
                ],
            ]),
            'poro' => $buildSameNeedPathways([
                'key' => 'poro',
                'barangay' => 'Poro',
                'label' => 'Brgy. Poro / Sevilla — Coastal Mental Wellness & Active Recovery',
                'category' => 'mental_wellbeing',
                'typology_badge' => 'Mental Wellness & Active Recovery',
                'lat' => 16.6095,
                'lng' => 120.3042,
                'zone' => 'PUD / Coastal Commercial Mixed-Use',
                'barriers' => ['affordability', 'hours', 'travel'],
                'intended_users' => 'Shift workers, youth & coastal residents in Brgy. Poro and Sevilla',
                'baseline_summary' => 'Current supply: Daytime gyms charge commercial membership fees and lack structured stress recovery & nutrition coaching after work.',
                'source' => 'Poro & Sevilla Community Wellness Poll',
                'modes' => [
                    'open' => [
                        'badge' => 'Option A · Open New',
                        'title' => 'New Coastal Active Recovery & Psychosocial Studio',
                        'subtitle' => 'Dedicated Baywalk parcel build-out for movement recovery, stress counseling, and nutrition workshops.',
                        'additional_access_summary' => '+5,030 catchment reach · +30 evening hrs/wk · full build-out',
                        'criteria' => $pathways['open']['evidence']['criteria'],
                        'scenario_input' => $pathways['open']['scenario_input'],
                    ],
                    'improve' => [
                        'badge' => 'Option B · Improve Existing',
                        'title' => 'Upgrade Coastal Studio (Quiet Recovery Wing + Evening Hours)',
                        'subtitle' => 'Adds acoustic counseling partitions, step-free access, and evening nutrition/stress classes at an existing facility.',
                        'additional_access_summary' => '+4,200 catchment reach · +20 evening hrs/wk · sound-isolated rooms',
                        'criteria' => $pathways['improve']['evidence']['criteria'],
                        'scenario_input' => $pathways['improve']['scenario_input'],
                    ],
                    'partner' => [
                        'badge' => 'Option C · Partner & Co-Locate',
                        'title' => 'Co-Locate Off-Peak Recovery & Nutrition Pass in Host Studio',
                        'subtitle' => 'Partners with a permitted Poro Point studio to run affordable ₱240/session evening recovery & nutrition blocks.',
                        'additional_access_summary' => '+5,030 catchment reach · +16 evening hrs/wk · ₱240 pass tier',
                        'criteria' => $pathways['partner']['evidence']['criteria'],
                        'scenario_input' => $pathways['partner']['scenario_input'],
                    ],
                ],
            ]),
        ];

        return [
            'pilot_title' => 'San Fernando City Barangay Wellness Comparison',
            'configuration' => $defaultConfig,
            'pathways' => $pathways,
            'need_scenarios' => $needScenarios,
            'barangay_profiles' => self::barangayProfiles(),
        ];
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
