<?php
declare(strict_types=1);
namespace App\Support;

final class WellnessPolicy
{
    public static function reviewer(?array $user): bool
    {
        return ($user['role'] ?? '') === 'admin'
            && in_array(strtoupper((string) ($user['department'] ?? '')), ['CICTO', 'ICT', 'ASSESSOR', 'CAO', 'LEBDO'], true)
            && ($user['accountStatus'] ?? $user['account_status'] ?? 'active') === 'active';
    }

    public static function canRead(array $record, ?array $user, bool $public = false): bool
    {
        return ($public && ($record['review_status'] ?? '') === 'reviewed')
            || self::reviewer($user)
            || ((int) ($user['id'] ?? 0) > 0 && ($user['accountStatus'] ?? $user['account_status'] ?? 'active') === 'active' && (int) ($record['owner_user_id'] ?? 0) === (int) $user['id']);
    }

    public static function canEdit(array $record, ?array $user): bool
    {
        return (int) ($user['id'] ?? 0) > 0 && ($user['accountStatus'] ?? $user['account_status'] ?? 'active') === 'active' && (int) ($record['owner_user_id'] ?? 0) === (int) $user['id'];
    }

    public static function canReview(array $record, ?array $user): bool
    {
        return self::reviewer($user) && (int) ($record['owner_user_id'] ?? 0) !== (int) ($user['id'] ?? 0);
    }
}
