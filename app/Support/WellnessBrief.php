<?php
declare(strict_types=1);
namespace App\Support;

/** Small self-contained text PDF; no remote fonts, HTML execution or URL fetching. */
final class WellnessBrief
{
    public static function lines(array $comparison): array
    {
        $lines = ['wELYU | Wellness investment comparison', $comparison['name'], 'Saved snapshot: ' . $comparison['snapshot_sha256'], 'Created: ' . $comparison['created_at'], 'Methodology: ' . ($comparison['snapshot']['methodology_version'] ?? 'See saved assessment versions'), ''];
        foreach ($comparison['snapshot']['assessments'] as $assessment) {
            $snapshot = $assessment['snapshot']; $proposal = $snapshot['proposal']; $result = $assessment['result'];
            $lines[] = 'PROPOSAL: ' . $proposal['title'] . ' | ' . $proposal['mode'];
            $lines[] = 'Concept: ' . $proposal['payload']['concept'];
            $lines[] = 'Intended users: ' . $proposal['payload']['intended_users'];
            $lines[] = 'Location: ' . ($proposal['payload']['location'] ?: 'See linked property / location verification required');
            $lines[] = 'Proposal review: ' . ($proposal['review_status'] ?? 'not recorded') . ' | Scope: ' . ($proposal['review']['scope'] ?? 'No scoped review recorded');
            $lines[] = 'Configuration: ' . $snapshot['configuration']['name'] . ' | ' . $snapshot['configuration']['validation_status'];
            $lines[] = 'Configuration reference: ' . ($snapshot['configuration']['validation_reference'] ?: 'Provisional; no validation reference');
            $lines[] = 'Assessment methodology: ' . ($assessment['methodology_version'] ?? $result['methodology_version'] ?? 'Saved version not specified');
            if (!empty($proposal['payload']['source_need_id'])) { $lines[] = 'Source community need: #' . $proposal['payload']['source_need_id']; }
            $lines[] = 'MCE: ' . ($result['mce_score'] ?? 'withheld') . ' | IAI: ' . ($result['iai_score'] ?? 'withheld') . ' | ' . $result['status'];
            $lines[] = $result['mce_formula'] ?? 'MCE: weighted normalized criteria; all six evidenced observations required.';
            $lines[] = $result['iai_formula'] ?? 'Wellness IAI: same MCE only after all five readiness fields have reviewed source-backed evidence.';
            $lines[] = $result['decision_thresholds'] ?? 'No automatic investment pass threshold is supplied.';
            $lines[] = 'Evidence coverage: ' . $result['evidence_coverage_pct'] . '%';
            foreach ($result['criteria'] as $criterion) {
                $lines[] = $criterion['label'] . ' | raw ' . ($criterion['raw'] ?? 'unknown') . ' | weight ' . $criterion['weight'] . '% | normalized ' . ($criterion['normalized'] === null ? 'unknown' : round($criterion['normalized'], 2));
                $lines[] = 'Rule: ' . $criterion['rule']['direction'] . ' [' . $criterion['rule']['min'] . ', ' . $criterion['rule']['max'] . '] ' . $criterion['rule']['unit'] . '; linear, clamped to 0-100';
                $lines[] = 'Source: ' . ($criterion['evidence']['source'] ?: 'missing') . ' | ' . ($criterion['evidence']['observed_on'] ?? 'date missing') . ' ' . $criterion['evidence']['url'];
            }
            foreach ($result['readiness'] as $key => $entry) { $lines[] = ucfirst($key) . ': ' . $entry['status'] . ' | ' . ($entry['evidence']['source'] ?: 'source missing') . ' | ' . ($entry['evidence']['observed_on'] ?? 'date missing') . ' | ' . $entry['notes'] . ' ' . ($entry['evidence']['url'] ?? ''); }
            $lines[] = 'Critical restrictions: ' . (implode(', ', $result['critical_restrictions']) ?: 'none recorded; not proof of clearance');
            $lines[] = 'Next verification: ' . (implode(', ', array_merge($result['missing_criteria'], $result['unresolved_requirements'])) ?: 'Confirm current evidence with the responsible offices before a decision.');
            foreach (($comparison['snapshot']['scenarios'][(string) $assessment['proposal_id']] ?? []) as $scenario) {
                $lines[] = 'SCENARIO: ' . $scenario['name'] . ($scenario['is_baseline'] ? ' (baseline)' : ' (alternative)');
                foreach ($scenario['result']['inputs'] as $key => $value) { $lines[] = str_replace('_', ' ', $key) . ': ' . ($value === null ? 'unknown' : number_format($value, 2)); }
                $lines[] = 'Break-even visits / month: ' . ($scenario['result']['break_even_visits_per_month'] ?? 'insufficient inputs / no positive margin');
                $lines[] = 'Estimated additional population reach: ' . ($scenario['result']['estimated_additional_population_reach'] ?? 'not calculated') . '; not customers';
                $lines[] = 'Assumptions: ' . $scenario['result']['assumptions'];
                $lines[] = 'Quote source: ' . ($scenario['result']['quote_evidence']['source'] ?: 'not supplied') . ' | ' . ($scenario['result']['quote_evidence']['observed_on'] ?? 'date missing');
                $lines[] = 'Already served coverage IDs: ' . implode(', ', $scenario['input']['existing_coverage_ids'] ?? []);
                foreach (($scenario['input']['coverage_cells'] ?? []) as $cell) { $lines[] = 'Coverage unit ' . $cell['id'] . ': ' . $cell['population'] . ' people | ' . $cell['source'] . ' | ' . $cell['observed_on']; }
            }
            $lines[] = $result['notice']; $lines[] = '';
        }
        $lines[] = 'This brief reproduces a saved comparison. It does not change historical property assessments or replace official verification.';
        return $lines;
    }

    public static function pdf(array $comparison): string
    {
        $lines = [];
        foreach (self::lines($comparison) as $line) {
            $line = iconv('UTF-8', 'Windows-1252//TRANSLIT//IGNORE', preg_replace('/[\x00-\x1f\x7f]/u', ' ', $line) ?? '') ?: '';
            foreach (explode("\n", wordwrap($line, 92, "\n", true)) as $wrapped) { $lines[] = $wrapped; }
        }
        $pages = array_chunk($lines, 52); $objects = [1 => '', 2 => '', 3 => '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>']; $kids = [];
        foreach ($pages as $index => $page) {
            $pageId = 4 + $index * 2; $streamId = $pageId + 1; $kids[] = $pageId . ' 0 R';
            $stream = "0.18 0.28 0.20 rg\nBT /F1 10 Tf 13 TL 40 800 Td\n";
            foreach ($page as $line) { $stream .= '(' . str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $line) . ") Tj T*\n"; }
            $stream .= '(Page ' . ($index + 1) . ' of ' . count($pages) . ") Tj\nET";
            $objects[$pageId] = '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 595 842] /Resources << /Font << /F1 3 0 R >> >> /Contents ' . $streamId . ' 0 R >>';
            $objects[$streamId] = '<< /Length ' . strlen($stream) . ">>\nstream\n" . $stream . "\nendstream";
        }
        $objects[1] = '<< /Type /Catalog /Pages 2 0 R >>'; $objects[2] = '<< /Type /Pages /Count ' . count($pages) . ' /Kids [' . implode(' ', $kids) . '] >>';
        ksort($objects); $pdf = "%PDF-1.4\n"; $offsets = [0];
        foreach ($objects as $id => $body) { $offsets[$id] = strlen($pdf); $pdf .= $id . " 0 obj\n" . $body . "\nendobj\n"; }
        $xref = strlen($pdf); $pdf .= 'xref' . "\n0 " . (count($objects) + 1) . "\n0000000000 65535 f \n";
        foreach (array_slice($offsets, 1) as $offset) { $pdf .= sprintf("%010d 00000 n \n", $offset); }
        return $pdf . 'trailer << /Size ' . (count($objects) + 1) . " /Root 1 0 R >>\nstartxref\n" . $xref . "\n%%EOF\n";
    }
}
