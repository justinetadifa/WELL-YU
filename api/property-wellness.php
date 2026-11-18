<?php
declare(strict_types=1);

require __DIR__ . '/_bootstrap.php';
require_once dirname(__DIR__) . '/app/Support/PropertyWellnessDiscovery.php';

api_handle(static function (array $container): array {
    header('Cache-Control: private, no-store');
    if (request_method() !== 'GET') {
        header('Allow: GET');
        return [405, ['error' => 'Method not allowed.']];
    }
    $actor = sfc_current_user();
    if ($actor === null) {
        return [401, ['error' => 'Sign in to explore the property desk.']];
    }
    if (($actor['role'] ?? '') !== 'admin' || !sfc_can_manage_properties($actor)) {
        return [403, ['error' => 'A city department account is required.']];
    }
    return \App\Support\PropertyWellnessDiscovery::snapshot($container['properties']->all($actor));
});
