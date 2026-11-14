<?php
declare(strict_types=1);
namespace App\Repositories;

use App\Support\{WellnessEngine, WellnessPolicy, WellnessRecords};
use PDO;
use PDOException;
use InvalidArgumentException;
use OutOfBoundsException;
use RuntimeException;
require_once dirname(__DIR__) . '/Support/WellnessPolicy.php';
require_once dirname(__DIR__) . '/Support/WellnessRecords.php';

final class WellnessRepository
{
    public const TABLES = ['welyu_configurations', 'welyu_services', 'welyu_needs', 'welyu_proposals', 'welyu_assessments', 'welyu_scenarios', 'welyu_comparisons', 'welyu_comparison_items', 'welyu_events', 'welyu_documents'];
    private const KINDS = ['service' => 'welyu_services', 'need' => 'welyu_needs', 'proposal' => 'welyu_proposals'];

    public function __construct(private PDO $pdo, private $propertyReader)
    {
        if ($pdo->query('SELECT DATABASE()')->fetchColumn() !== 'well_yu_db') { throw new RuntimeException('Wellness features require well_yu_db.'); }
    }

    private function table(string $name): string
    {
        if (!in_array($name, self::TABLES, true)) { throw new InvalidArgumentException('Unsupported wellness table.'); }
        return '`well_yu_db`.`' . $name . '`';
    }

    private function query(string $sql, array $params = []): \PDOStatement
    {
        $statement = $this->pdo->prepare($sql); $statement->execute($params); return $statement;
    }

    public function readiness(): array
    {
        $present = $this->query("SELECT TABLE_NAME FROM information_schema.tables WHERE TABLE_SCHEMA = 'well_yu_db' AND TABLE_TYPE = 'BASE TABLE'")->fetchAll(PDO::FETCH_COLUMN);
        $missing = array_values(array_diff(self::TABLES, $present));
        $missingColumns = [];
        if ($missing === []) {
            $actual = [];
            foreach ($this->query("SELECT TABLE_NAME, COLUMN_NAME FROM information_schema.columns WHERE TABLE_SCHEMA = 'well_yu_db'")->fetchAll() as $column) { $actual[$column['TABLE_NAME']][] = $column['COLUMN_NAME']; }
            $sql = (string) file_get_contents(dirname(__DIR__, 2) . '/database/well-yu/01-wellness.sql');
            preg_match_all('/CREATE TABLE IF NOT EXISTS (welyu_\w+) \((.*?)^\) ENGINE=/ms', $sql, $definitions, PREG_SET_ORDER);
            foreach ($definitions as $definition) { preg_match_all('/^  ([a-z][a-z0-9_]*) [A-Z]/m', $definition[2], $columns); foreach (array_diff($columns[1], $actual[$definition[1]] ?? []) as $name) { $missingColumns[] = $definition[1] . '.' . $name; } }
        }
        return ['ready' => $missing === [] && $missingColumns === [], 'missing_table_count' => count($missing), 'missing_column_count' => count($missingColumns)];
    }

    private function transaction(callable $operation): mixed
    {
        $this->pdo->beginTransaction();
        try { $result = $operation(); $this->pdo->commit(); return $result; }
        catch (\Throwable $error) { if ($this->pdo->inTransaction()) { $this->pdo->rollBack(); } throw $error; }
    }

    private function insert(string $table, array $values): int
    {
        $fields = array_keys($values);
        foreach ($fields as $field) { if (!preg_match('/^[a-z_][a-z0-9_]*$/D', $field)) { throw new InvalidArgumentException('Invalid field.'); } }
        $this->query('INSERT INTO ' . $this->table($table) . ' (`' . implode('`,`', $fields) . '`) VALUES (:' . implode(',:', $fields) . ')', $values);
        return (int) $this->pdo->lastInsertId();
    }

    private static function json(array $data): string { return json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRESERVE_ZERO_FRACTION | JSON_THROW_ON_ERROR); }

    private function actor(array $user): int
    {
        $id = (int) ($user['id'] ?? 0);
        if ($id < 1 || ($user['accountStatus'] ?? $user['account_status'] ?? 'active') !== 'active') { throw new InvalidArgumentException('An active signed-in account is required.'); }
        return $id;
    }

    private function hydrate(array $row): array
    {
        foreach (['payload_json' => 'payload', 'review_json' => 'review', 'snapshot_json' => 'snapshot', 'result_json' => 'result', 'input_json' => 'input'] as $field => $key) {
            if (array_key_exists($field, $row)) { $row[$key] = json_decode((string) ($row[$field] ?? '{}'), true, 64, JSON_THROW_ON_ERROR) ?: []; unset($row[$field]); }
        }
        foreach (['id', 'owner_user_id', 'created_by_user_id', 'configuration_id', 'proposal_id', 'property_id', 'service_id', 'need_id', 'baseline_id', 'revision'] as $field) { if (isset($row[$field])) { $row[$field] = (int) $row[$field]; } }
        foreach (['lat', 'lng'] as $field) { if (isset($row[$field])) { $row[$field] = (float) $row[$field]; } }
        return $row;
    }

    private function privateFields(array $row, ?array $actor): array
    {
        if (!WellnessPolicy::canRead($row, $actor)) {
            unset($row['owner_user_id'], $row['reviewer_user_id'], $row['fingerprint']);
            $row['review'] = ['scope' => $row['review']['scope'] ?? '', 'reviewed_on' => $row['review']['reviewed_on'] ?? null];
        }
        $date = $row['payload']['verified_on'] ?? $row['review']['reviewed_on'] ?? null;
        $row['outdated'] = $date === null || $date < date('Y-m-d', strtotime('-1 year'));
        return $row;
    }

    public function list(string $kind, ?array $actor, bool $mine = false): array
    {
        $table = self::KINDS[$kind] ?? throw new InvalidArgumentException('Unsupported record kind.');
        $owner = (int) ($actor['id'] ?? 0); $params = [];
        if (WellnessPolicy::reviewer($actor) && !$mine) { $where = ''; }
        elseif ($mine || $kind === 'proposal') { $where = ' WHERE owner_user_id = :owner'; $params['owner'] = $owner; }
        else { $where = " WHERE (review_status = 'reviewed' OR owner_user_id = :owner)"; $params['owner'] = $owner; }
        $rows = $this->query('SELECT * FROM ' . $this->table($table) . $where . ' ORDER BY updated_at DESC, id DESC LIMIT 500', $params)->fetchAll();
        return array_map(fn (array $row): array => $this->privateFields($this->hydrate($row), $actor), $rows);
    }

    public function record(string $kind, int $id, ?array $actor, bool $private = false): array
    {
        $table = self::KINDS[$kind] ?? throw new InvalidArgumentException('Unsupported record kind.');
        $raw = $this->query('SELECT * FROM ' . $this->table($table) . ' WHERE id = :id', ['id' => $id])->fetch();
        if (!$raw || !WellnessPolicy::canRead($raw, $actor, !$private && $kind !== 'proposal')) { throw new OutOfBoundsException('Record is unavailable to this account.'); }
        $row = $this->hydrate($raw);
        if ($kind === 'proposal' && !empty($row['property_id'])) { ($this->propertyReader)((int) $row['property_id'], $actor); }
        return $this->privateFields($row, $actor);
    }

    public function saveDirectory(string $kind, array $input, array $actor): array
    {
        $owner = $this->actor($actor); $payload = WellnessRecords::normalize($kind, $input);
        $table = self::KINDS[$kind]; $id = (int) ($input['id'] ?? 0);
        $values = ['title' => $payload['title'], 'category' => $payload['category'], 'barangay' => $payload['barangay'], 'lat' => $payload['lat'], 'lng' => $payload['lng'], 'payload_json' => self::json($payload), 'fingerprint' => WellnessRecords::fingerprint($kind, $payload, $owner)];
        return $this->transaction(function () use ($kind, $actor, $input, $owner, $table, $id, $values): array {
            try {
                if ($id > 0) {
                    $existing = $this->record($kind, $id, $actor, true);
                    if (!WellnessPolicy::canEdit($existing, $actor)) { throw new OutOfBoundsException('Only the record owner can revise it.'); }
                    $values['id'] = $id; $values['revision'] = (int) ($input['revision'] ?? 0);
                    $statement = $this->query('UPDATE ' . $this->table($table) . " SET title=:title, category=:category, barangay=:barangay, lat=:lat, lng=:lng, payload_json=:payload_json, fingerprint=:fingerprint, review_status='submitted', review_json=NULL, reviewer_user_id=NULL, revision=revision+1 WHERE id=:id AND revision=:revision", $values);
                    if ($statement->rowCount() !== 1) { throw new InvalidArgumentException('This record changed. Reload before revising it.'); }
                } else { $id = $this->insert($table, ['owner_user_id' => $owner] + $values); }
            } catch (PDOException $error) {
                if ($error->getCode() === '23000') { throw new InvalidArgumentException('A matching record already exists. Review it or report a correction; no duplicate was inserted.'); }
                throw $error;
            }
            $this->event($kind, $id, $actor, 'submitted', ['revision' => ($values['revision'] ?? 0) + 1]);
            return $this->record($kind, $id, $actor, true);
        });
    }

    public function importPreview(string $kind, array $records, array $actor): array
    {
        $owner = $this->actor($actor);
        if (!WellnessPolicy::reviewer($actor) || !in_array($kind, ['service', 'need'], true) || !WellnessEngine::isList($records) || count($records) < 1 || count($records) > 50) { throw new InvalidArgumentException('Authorized imports accept an array of 1–50 service or need records.'); }
        $rows = []; $seen = [];
        foreach ($records as $index => $record) {
            if (!is_array($record) || !empty($record['id'])) { throw new InvalidArgumentException('Imports only create new, explicitly reviewed input rows.'); }
            $normalized = WellnessRecords::normalize($kind, $record); $fingerprint = WellnessRecords::fingerprint($kind, $normalized, $owner);
            $duplicate = isset($seen[$fingerprint]) || (int) $this->query('SELECT COUNT(*) FROM ' . $this->table(self::KINDS[$kind]) . ' WHERE fingerprint = :fingerprint', ['fingerprint' => $fingerprint])->fetchColumn() > 0;
            $rows[] = ['row' => $index + 1, 'record' => $normalized, 'duplicate' => $duplicate]; $seen[$fingerprint] = true;
        }
        return ['rows' => $rows, 'input_sha256' => WellnessEngine::hash($records), 'can_commit' => !in_array(true, array_column($rows, 'duplicate'), true)];
    }

    public function importCommit(string $kind, array $records, string $hash, array $actor): array
    {
        $preview = $this->importPreview($kind, $records, $actor);
        if (!$preview['can_commit'] || !hash_equals($preview['input_sha256'], $hash)) { throw new InvalidArgumentException('Import conflicts or changed input require another preview.'); }
        return $this->transaction(function () use ($kind, $actor, $preview): array {
            $ids = [];
            foreach ($preview['rows'] as $row) {
                $p = $row['record']; $owner = $this->actor($actor);
                $id = $this->insert(self::KINDS[$kind], ['owner_user_id' => $owner, 'title' => $p['title'], 'category' => $p['category'], 'barangay' => $p['barangay'], 'lat' => $p['lat'], 'lng' => $p['lng'], 'payload_json' => self::json($p), 'fingerprint' => WellnessRecords::fingerprint($kind, $p, $owner)]);
                $this->event($kind, $id, $actor, 'submitted', ['imported' => true]); $ids[] = $id;
            }
            return ['ids' => $ids, 'review_status' => 'submitted'];
        });
    }

    public function createProposal(array $input, array $actor): array
    {
        $owner = $this->actor($actor); $mode = $input['mode'] ?? 'open';
        if (!in_array($mode, ['open', 'improve', 'partner'], true)) { throw new InvalidArgumentException('Choose open, improve or partner.'); }
        $propertyId = (int) ($input['property_id'] ?? 0); $serviceId = (int) ($input['service_id'] ?? 0); $needId = (int) ($input['source_need_id'] ?? 0);
        if ($needId > 0) { $this->record('need', $needId, $actor); }
        $property = $propertyId > 0 ? ($this->propertyReader)($propertyId, $actor) : null;
        if ($mode !== 'open' && $serviceId < 1) { throw new InvalidArgumentException('Choose an existing service for an improvement or partnership.'); }
        if ($serviceId > 0) {
            $service = $this->record('service', $serviceId, $actor);
            if ($mode === 'improve' && !WellnessPolicy::canEdit($service, $actor)) { throw new InvalidArgumentException('Improvement plans require ownership of the service record.'); }
        }
        $category = $input['category'] ?? '';
        if (!in_array($category, WellnessEngine::CATEGORIES, true)) { throw new InvalidArgumentException('Choose a service category.'); }
        $complements = $input['complementary_categories'] ?? [];
        if (!is_array($complements) || array_filter($complements, static fn ($value): bool => !is_string($value)) || array_diff($complements, WellnessEngine::CATEGORIES)) { throw new InvalidArgumentException('Choose supported complementary categories.'); }
        $payload = ['source_need_id' => $needId ?: null, 'concept' => WellnessEngine::text($input['concept'] ?? '', 4000), 'intended_users' => WellnessEngine::text($input['intended_users'] ?? '', 600), 'category' => $category, 'location' => WellnessEngine::text($input['location'] ?? '', 600, false), 'lat' => WellnessEngine::number($property['lat'] ?? $input['lat'] ?? null, -90, 90), 'lng' => WellnessEngine::number($property['lng'] ?? $input['lng'] ?? null, -180, 180), 'barriers_and_improvements' => WellnessEngine::text($input['barriers_and_improvements'] ?? '', 4000, $mode === 'improve'), 'evidence' => WellnessEngine::evidence($input['evidence'] ?? [], $mode === 'improve'), 'complementary_categories' => array_values(array_unique($complements))];
        if (($payload['lat'] === null) !== ($payload['lng'] === null)) { throw new InvalidArgumentException('Record both coordinates or leave both unknown.'); }
        return $this->transaction(function () use ($owner, $input, $mode, $propertyId, $serviceId, $payload, $actor): array {
            $id = (int) ($input['id'] ?? 0);
            $values = ['title' => WellnessEngine::text($input['title'] ?? ''), 'mode' => $mode, 'property_id' => $propertyId ?: null, 'service_id' => $serviceId ?: null, 'payload_json' => self::json($payload)];
            if ($id > 0) {
                $existing = $this->record('proposal', $id, $actor, true);
                if (!WellnessPolicy::canEdit($existing, $actor)) { throw new OutOfBoundsException('Only the owner can revise a proposal.'); }
                $changed = $this->query('UPDATE ' . $this->table('welyu_proposals') . " SET title=:title, mode=:mode, property_id=:property_id, service_id=:service_id, payload_json=:payload_json, review_status='submitted', review_json=NULL, reviewer_user_id=NULL, revision=revision+1 WHERE id=:id AND revision=:revision", $values + ['id' => $id, 'revision' => (int) ($input['revision'] ?? 0)]);
                if ($changed->rowCount() !== 1) { throw new InvalidArgumentException('Proposal changed. Reload before revising.'); }
            } else { $id = $this->insert('welyu_proposals', ['owner_user_id' => $owner] + $values); }
            $this->event('proposal', $id, $actor, 'submitted', ['mode' => $mode]);
            return $this->record('proposal', $id, $actor, true);
        });
    }

    public function configurations(): array
    {
        return array_map(fn (array $row): array => $this->hydrate($row), $this->query('SELECT * FROM ' . $this->table('welyu_configurations') . ' ORDER BY id DESC LIMIT 100')->fetchAll());
    }

    public function createConfiguration(array $input, array $actor): array
    {
        if (!WellnessPolicy::reviewer($actor)) { throw new OutOfBoundsException('City configuration access required.'); }
        $payload = WellnessEngine::configuration($input);
        $id = $this->insert('welyu_configurations', ['created_by_user_id' => $this->actor($actor), 'name' => $payload['name'], 'methodology_version' => WellnessEngine::VERSION, 'payload_json' => self::json($payload), 'validation_status' => $payload['validation_status'], 'validation_reference' => $payload['validation_reference'] ?: null]);
        return $this->hydrate($this->query('SELECT * FROM ' . $this->table('welyu_configurations') . ' WHERE id=:id', ['id' => $id])->fetch());
    }

    public function configuration(int $id): array
    {
        $row = $this->query('SELECT * FROM ' . $this->table('welyu_configurations') . ' WHERE id=:id', ['id' => $id])->fetch();
        if (!$row) { throw new OutOfBoundsException('Choose a saved scoring configuration.'); }
        return $this->hydrate($row);
    }

    public function assess(array $input, array $actor): array
    {
        $proposal = $this->record('proposal', (int) ($input['proposal_id'] ?? 0), $actor, true);
        $configuration = $this->configuration((int) ($input['configuration_id'] ?? 0));
        $input = self::scopeEvidence($input, WellnessPolicy::canReview($proposal, $actor));
        $result = WellnessEngine::assess($input, $configuration['payload']);
        $snapshot = ['proposal' => $proposal, 'configuration' => $configuration, 'observations' => $input, 'property_id' => $proposal['property_id']];
        $hash = WellnessEngine::hash($snapshot);
        $existing = $this->query('SELECT id FROM ' . $this->table('welyu_assessments') . ' WHERE proposal_id=:proposal AND input_sha256=:hash', ['proposal' => $proposal['id'], 'hash' => $hash])->fetchColumn();
        if ($existing) { return $this->assessment((int) $existing, $actor); }
        $id = $this->insert('welyu_assessments', ['proposal_id' => $proposal['id'], 'created_by_user_id' => $this->actor($actor), 'configuration_id' => $configuration['id'], 'methodology_version' => WellnessEngine::VERSION, 'snapshot_json' => self::json($snapshot), 'result_json' => self::json($result), 'input_sha256' => $hash]);
        return $this->assessment($id, $actor);
    }

    public static function scopeEvidence(array $input, bool $reviewer): array
    {
        foreach (['criteria', 'readiness'] as $section) { foreach (WellnessEngine::object($input[$section] ?? []) as $key => $value) { $value = WellnessEngine::object($value); if (!$reviewer && ($value['status'] ?? '') === 'reviewed') { $input[$section][$key]['status'] = 'reported'; } } }
        return $input;
    }

    public function pipeline(?array $actor): array
    {
        if ($actor === null) { return []; }
        $rows = [];
        foreach ($this->list('proposal', $actor) as $proposal) {
            $events = $this->events('proposal', $proposal['id'], $actor); $implementation = null;
            foreach ($events as $event) { if ($event['kind'] === 'implemented') { $implementation = $event; break; } }
            $rows[] = ['id' => $proposal['id'], 'title' => $proposal['title'], 'mode' => $proposal['mode'], 'review_status' => $proposal['review_status'], 'stage' => $implementation === null ? 'proposal' : 'implementation_reported', 'latest_event' => $events[0] ?? null, 'implementation_evidence' => $implementation['payload']['evidence'] ?? null];
        }
        return $rows;
    }

    public function assessment(int $id, array $actor): array
    {
        $row = $this->query('SELECT * FROM ' . $this->table('welyu_assessments') . ' WHERE id=:id', ['id' => $id])->fetch();
        if (!$row) { throw new OutOfBoundsException('Assessment not found.'); }
        $this->record('proposal', (int) $row['proposal_id'], $actor, true); $row = $this->hydrate($row);
        if (!empty($row['snapshot']['property_id'])) { ($this->propertyReader)((int) $row['snapshot']['property_id'], $actor); }
        return $row;
    }

    public function assessments(array $actor): array
    {
        $rows = $this->query('SELECT a.id FROM ' . $this->table('welyu_assessments') . ' a JOIN ' . $this->table('welyu_proposals') . ' p ON p.id=a.proposal_id' . (WellnessPolicy::reviewer($actor) ? '' : ' WHERE p.owner_user_id=:owner') . ' ORDER BY a.id DESC LIMIT 100', WellnessPolicy::reviewer($actor) ? [] : ['owner' => $this->actor($actor)])->fetchAll(PDO::FETCH_COLUMN);
        return array_map(fn ($id): array => $this->assessment((int) $id, $actor), $rows);
    }

    public function saveScenario(array $input, array $actor): array
    {
        $proposal = $this->record('proposal', (int) ($input['proposal_id'] ?? 0), $actor, true);
        if (!WellnessPolicy::canEdit($proposal, $actor)) { throw new OutOfBoundsException('Only the proposal owner can save scenarios.'); }
        $baselineId = (int) ($input['baseline_id'] ?? 0); $baseline = null;
        if ($baselineId > 0) {
            $row = $this->query('SELECT * FROM ' . $this->table('welyu_scenarios') . ' WHERE id=:id AND proposal_id=:proposal AND is_baseline=1', ['id' => $baselineId, 'proposal' => $proposal['id']])->fetch();
            if (!$row) { throw new InvalidArgumentException('The baseline must belong to this proposal.'); }
            $baseline = $this->hydrate($row)['result'];
        }
        $result = WellnessEngine::scenario($input, $baseline);
        $id = $this->insert('welyu_scenarios', ['proposal_id' => $proposal['id'], 'owner_user_id' => $this->actor($actor), 'baseline_id' => $baselineId ?: null, 'name' => WellnessEngine::text($input['name'] ?? ''), 'is_baseline' => $baselineId === 0 ? 1 : 0, 'input_json' => self::json($input), 'result_json' => self::json($result)]);
        return $this->hydrate($this->query('SELECT * FROM ' . $this->table('welyu_scenarios') . ' WHERE id=:id', ['id' => $id])->fetch());
    }

    public function scenarios(int $proposalId, array $actor): array
    {
        $this->record('proposal', $proposalId, $actor, true);
        return array_map(fn (array $row): array => $this->hydrate($row), $this->query('SELECT * FROM ' . $this->table('welyu_scenarios') . ' WHERE proposal_id=:id ORDER BY id', ['id' => $proposalId])->fetchAll());
    }

    public function compare(array $input, array $actor): array
    {
        $ids = $input['assessment_ids'] ?? [];
        if (!is_array($ids) || count($ids) < 2 || count($ids) > 5 || count(array_unique($ids)) !== count($ids)) { throw new InvalidArgumentException('Choose 2–5 distinct assessments.'); }
        $assessments = array_map(fn ($id): array => $this->assessment((int) $id, $actor), $ids);
        $snapshot = ['methodology_version' => WellnessEngine::VERSION, 'assessments' => $assessments, 'scenarios' => []];
        foreach ($assessments as $assessment) { $snapshot['scenarios'][(string) $assessment['proposal_id']] = $this->scenarios((int) $assessment['proposal_id'], $actor); }
        return $this->transaction(function () use ($input, $actor, $snapshot, $ids): array {
            $id = $this->insert('welyu_comparisons', ['owner_user_id' => $this->actor($actor), 'name' => WellnessEngine::text($input['name'] ?? ''), 'snapshot_json' => self::json($snapshot), 'snapshot_sha256' => WellnessEngine::hash($snapshot)]);
            foreach ($ids as $assessmentId) { $this->insert('welyu_comparison_items', ['comparison_id' => $id, 'assessment_id' => (int) $assessmentId]); }
            return $this->comparison($id, $actor);
        });
    }

    public function comparison(int $id, array $actor): array
    {
        $row = $this->query('SELECT * FROM ' . $this->table('welyu_comparisons') . ' WHERE id=:id', ['id' => $id])->fetch();
        if (!$row || !WellnessPolicy::canRead($row, $actor)) { throw new OutOfBoundsException('Comparison unavailable.'); }
        $row = $this->hydrate($row);
        foreach ($row['snapshot']['assessments'] as $assessment) { $this->assessment((int) $assessment['id'], $actor); }
        return $row;
    }

    public function comparisons(array $actor): array
    {
        $ids = $this->query('SELECT id FROM ' . $this->table('welyu_comparisons') . (WellnessPolicy::reviewer($actor) ? '' : ' WHERE owner_user_id=:owner') . ' ORDER BY id DESC LIMIT 100', WellnessPolicy::reviewer($actor) ? [] : ['owner' => $this->actor($actor)])->fetchAll(PDO::FETCH_COLUMN);
        return array_map(fn ($id): array => $this->comparison((int) $id, $actor), $ids);
    }

    public function event(string $kind, int $id, array $actor, string $event, array $payload): int
    {
        if (!isset(self::KINDS[$kind])) { throw new InvalidArgumentException('Unsupported event parent.'); }
        return $this->insert('welyu_events', ['actor_user_id' => $this->actor($actor), $kind . '_id' => $id, 'kind' => $event, 'payload_json' => self::json($payload)]);
    }

    public function review(array $input, array $actor): array
    {
        $kind = $input['kind'] ?? ''; $id = (int) ($input['id'] ?? 0); $record = $this->record($kind, $id, $actor, true);
        if (!WellnessPolicy::canReview($record, $actor)) { throw new OutOfBoundsException('An authorized city reviewer other than the submitter is required.'); }
        $status = $input['status'] ?? '';
        $allowed = ['submitted' => ['under_review'], 'under_review' => ['evidence_required', 'reviewed'], 'evidence_required' => ['under_review'], 'reviewed' => ['under_review']];
        if (!in_array($status, $allowed[$record['review_status']] ?? [], true)) { throw new InvalidArgumentException('Choose the next permitted review state.'); }
        $review = ['scope' => WellnessEngine::text($input['scope'] ?? '', 2000), 'notes' => WellnessEngine::text($input['notes'] ?? '', 4000), 'reviewed_on' => date('Y-m-d'), 'prior_revision' => $record['revision']];
        return $this->transaction(function () use ($kind, $id, $record, $actor, $status, $review): array {
            $changed = $this->query('UPDATE ' . $this->table(self::KINDS[$kind]) . ' SET review_status=:status, review_json=:review, reviewer_user_id=:actor, revision=revision+1 WHERE id=:id AND revision=:revision', ['status' => $status, 'review' => self::json($review), 'actor' => $this->actor($actor), 'id' => $id, 'revision' => $record['revision']]);
            if ($changed->rowCount() !== 1) { throw new InvalidArgumentException('Record changed during review; reload it.'); }
            $this->event($kind, $id, $actor, $status, $review);
            return $this->record($kind, $id, $actor, true);
        });
    }

    public function addFollowUp(array $input, array $actor): array
    {
        $kind = $input['record_kind'] ?? 'proposal'; $id = (int) ($input['record_id'] ?? 0);
        $event = $input['kind'] ?? '';
        $record = $this->record($kind, $id, $actor, $event !== 'correction_report');
        if (!in_array($event, ['confirmed_inquiry', 'site_visit', 'decision', 'implemented', 'observed_follow_up', 'correction_report', 'correction_response'], true)) { throw new InvalidArgumentException('Choose a supported follow-up event.'); }
        if ($event !== 'correction_report' && !WellnessPolicy::canEdit($record, $actor) && !WellnessPolicy::reviewer($actor)) { throw new OutOfBoundsException('Owner or reviewer access required.'); }
        if ($event === 'correction_response' && !WellnessPolicy::reviewer($actor)) { throw new OutOfBoundsException('Reviewer access required.'); }
        $payload = ['notes' => WellnessEngine::text($input['notes'] ?? '', 4000), 'responsible' => WellnessEngine::text($input['responsible'] ?? '', 190), 'next_action' => WellnessEngine::text($input['next_action'] ?? '', 2000), 'evidence' => WellnessEngine::evidence($input['evidence'] ?? [], true), 'observed' => $event === 'observed_follow_up', 'metrics' => []];
        if ($event === 'correction_response') {
            $report = $this->query('SELECT * FROM ' . $this->table('welyu_events') . " WHERE id=:id AND kind='correction_report' AND " . $kind . '_id=:parent', ['id' => (int) ($input['responds_to'] ?? 0), 'parent' => $id])->fetch();
            if (!$report) { throw new InvalidArgumentException('Choose the correction report this response resolves.'); }
            $payload['responds_to'] = (int) $report['id'];
        }
        if ($event === 'observed_follow_up') {
            WellnessEngine::object($input['metrics'] ?? []);
            foreach (['price_php', 'weekly_hours', 'capacity_per_week', 'aggregate_usage'] as $key) { $payload['metrics'][$key] = WellnessEngine::number($input['metrics'][$key] ?? null); }
            if ($payload['metrics']['weekly_hours'] !== null && $payload['metrics']['weekly_hours'] > 168) { throw new InvalidArgumentException('Observed weekly hours cannot exceed 168.'); }
            foreach (['capacity_per_week', 'aggregate_usage'] as $key) { $value = $payload['metrics'][$key]; if ($value !== null && floor($value) !== $value) { throw new InvalidArgumentException('Observed aggregate counts must be whole numbers.'); } }
            $payload['metrics']['availability'] = WellnessEngine::text($input['metrics']['availability'] ?? '', 600, false);
        }
        $eventId = $this->event($kind, $id, $actor, $event, $payload);
        return ['id' => $eventId, 'kind' => $event, 'payload' => $payload];
    }

    public function corrections(?array $actor): array
    {
        if (!WellnessPolicy::reviewer($actor)) { return []; }
        $rows = $this->query('SELECT report.* FROM ' . $this->table('welyu_events') . ' report WHERE report.kind=\'correction_report\' AND NOT EXISTS (SELECT 1 FROM ' . $this->table('welyu_events') . " response WHERE response.kind='correction_response' AND JSON_UNQUOTE(JSON_EXTRACT(response.payload_json, '$.responds_to'))=CAST(report.id AS CHAR)) ORDER BY report.id DESC LIMIT 200")->fetchAll();
        return array_map(fn (array $row): array => $this->hydrate($row), $rows);
    }

    public function events(string $kind, int $id, array $actor): array
    {
        $this->record($kind, $id, $actor, true);
        return array_map(fn (array $row): array => $this->hydrate($row), $this->query('SELECT * FROM ' . $this->table('welyu_events') . ' WHERE ' . $kind . '_id=:id ORDER BY id DESC LIMIT 200', ['id' => $id])->fetchAll());
    }

    public function addDocument(string $kind, int $id, array $metadata, array $actor): int
    {
        $record = $this->record($kind, $id, $actor, true);
        if (!WellnessPolicy::canEdit($record, $actor) && !WellnessPolicy::reviewer($actor)) { throw new OutOfBoundsException('Document access denied.'); }
        return $this->insert('welyu_documents', ['uploader_user_id' => $this->actor($actor), $kind . '_id' => $id] + $metadata);
    }

    public function documents(string $kind, int $id, array $actor): array
    {
        $this->record($kind, $id, $actor, true);
        return $this->query('SELECT id, original_name, mime_type, size_bytes, sha256, created_at FROM ' . $this->table('welyu_documents') . ' WHERE ' . $kind . '_id=:id ORDER BY id', ['id' => $id])->fetchAll();
    }

    public function document(int $id, array $actor): array
    {
        $row = $this->query('SELECT * FROM ' . $this->table('welyu_documents') . ' WHERE id=:id', ['id' => $id])->fetch();
        if (!$row) { throw new OutOfBoundsException('Document not found.'); }
        foreach (self::KINDS as $kind => $_) { if (!empty($row[$kind . '_id'])) { $this->record($kind, (int) $row[$kind . '_id'], $actor, true); return $row; } }
        throw new OutOfBoundsException('Document parent unavailable.');
    }
}
