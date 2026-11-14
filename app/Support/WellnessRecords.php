<?php
declare(strict_types=1);
namespace App\Support;

use InvalidArgumentException;
require_once __DIR__ . '/WellnessEngine.php';

final class WellnessRecords
{
    public static function normalize(string $kind, array $input): array
    {
        $record = ['title' => WellnessEngine::text($input['title'] ?? ''), 'category' => WellnessEngine::text($input['category'] ?? ''), 'barangay' => WellnessEngine::text($input['barangay'] ?? '', 120)];
        if (!in_array($record['category'], WellnessEngine::CATEGORIES, true)) { throw new InvalidArgumentException('Choose a supported wellness service category.'); }
        require_once __DIR__ . '/PropertyCatalog.php';
        $barangays = PropertyCatalog::barangays(); $allowed = WellnessEngine::isList($barangays) ? $barangays : array_keys($barangays);
        if (!in_array($record['barangay'], $allowed, true)) { throw new InvalidArgumentException('Choose a San Fernando barangay.'); }
        $record['lat'] = WellnessEngine::number($input['lat'] ?? null, -90, 90);
        $record['lng'] = WellnessEngine::number($input['lng'] ?? null, -180, 180);
        if (($record['lat'] === null) !== ($record['lng'] === null)) { throw new InvalidArgumentException('Enter both latitude and longitude, or leave both unknown.'); }
        $record['evidence'] = WellnessEngine::evidence($input['evidence'] ?? [], true);
        $record['notes'] = WellnessEngine::text($input['notes'] ?? '', 4000, false);
        if ($kind === 'service') {
            $record['operator_kind'] = $input['operator_kind'] ?? 'private';
            if (!in_array($record['operator_kind'], ['private', 'public', 'nonprofit'], true)) { throw new InvalidArgumentException('Choose a supported operator kind.'); }
            foreach (['is_free', 'open_to_partnership'] as $key) {
                if (isset($input[$key]) && !is_bool($input[$key])) { throw new InvalidArgumentException('Use boolean values for service flags.'); }
                $record[$key] = $input[$key] ?? null;
            }
            $record['price_php'] = WellnessEngine::number($input['price_php'] ?? null);
            if ($record['is_free'] === true && $record['price_php'] !== null && $record['price_php'] !== 0.0) { throw new InvalidArgumentException('A free service cannot have a positive recorded fee.'); }
            $record['hours'] = WellnessEngine::text($input['hours'] ?? '', 600, false);
            $record['capacity_per_week'] = WellnessEngine::number($input['capacity_per_week'] ?? null, 0, 10000000);
            $record['accessibility'] = WellnessEngine::text($input['accessibility'] ?? '', 2000, false);
            $record['verified_on'] = WellnessEngine::date($input['verified_on'] ?? null, false);
        } elseif ($kind === 'need') {
            $record['intended_users'] = WellnessEngine::text($input['intended_users'] ?? '', 600);
            $barriers = $input['barriers'] ?? [];
            if (!is_array($barriers) || array_filter($barriers, static fn ($value): bool => !is_string($value)) || array_diff($barriers, WellnessEngine::BARRIERS)) { throw new InvalidArgumentException('Choose supported access barriers.'); }
            $record['barriers'] = array_values(array_unique($barriers));
        } else { throw new InvalidArgumentException('Unsupported directory record type.'); }
        return $record;
    }

    public static function fingerprint(string $kind, array $record, int $owner): string
    {
        $identity = [$kind, mb_strtolower($record['title']), $record['category'], $record['barangay'], $record['lat'], $record['lng']];
        if ($kind === 'need') { $identity[] = $owner; $identity[] = mb_strtolower($record['intended_users']); $identity[] = $record['evidence']['observed_on']; }
        return WellnessEngine::hash($identity);
    }
}
