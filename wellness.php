<?php
declare(strict_types=1);
require __DIR__ . '/app/Support/web.php';
$context = sfc_web_context();
sfc_render_head('San Fernando City — Wellness Prioritization Workspace', $context, [
    'page' => 'city-wellness',
    'role' => $context['user']['role'] ?? 'guest',
]);
?>
<link rel="stylesheet" href="<?= htmlspecialchars($context['assetBase'], ENT_QUOTES, 'UTF-8') ?>/css/wellness.css<?= sfc_asset_version('css/wellness.css') ?>">
<?php
sfc_render_header($context, 'wellness');
?>

<main class="city-container wy-workspace" id="wellnessWorkspace" aria-labelledby="wellnessTitle">
  <header class="wy-intro">
    <div class="wy-intro-copy">
      <p class="wy-kicker">San Fernando City · Wellness &amp; Land Intelligence</p>
      <h1 id="wellnessTitle">A thoughtful investment.<br><em>A healthier possibility.</em></h1>
      <p class="wy-lead">Connect a community&rsquo;s documented health and access barrier to comparable investment responses&mdash;open new, improve existing, or partner.</p>
    </div>
    <div class="wy-intro-actions">
      <button type="button" class="wy-btn wy-btn-primary" data-open-modal="need">+ Report Community Barrier</button>
      <button type="button" class="wy-btn wy-btn-secondary" data-open-modal="service">+ Record Service</button>
      <a class="wy-btn wy-btn-ghost" href="<?= htmlspecialchars(sfc_path('/api/wellness.php?action=export_pilot_brief'), ENT_QUOTES, 'UTF-8') ?>" target="_blank" rel="noopener">Export PDF Brief</a>
    </div>
  </header>

  <div id="wellnessNotice" class="wy-notice" role="status" aria-live="polite">Loading wellness workspace…</div>

  <nav class="wy-tabs" aria-label="Wellness prioritization workflow" id="wellnessTabs"></nav>
  <section id="wellnessView" aria-live="polite"><div class="wy-empty">Loading community needs and baseline services…</div></section>

  <!-- Modal: Report Community Barrier -->
  <dialog id="wyNeedModal" class="wy-modal" aria-labelledby="wyNeedModalTitle">
    <div class="wy-modal-head">
      <div>
        <span class="wy-kicker">Community Evidence</span>
        <h2 id="wyNeedModalTitle">Report a Community Health or Access Barrier</h2>
      </div>
      <button type="button" class="wy-modal-close" data-close-modal="wyNeedModal" aria-label="Close modal">×</button>
    </div>
    <p class="wy-muted">Describe an aggregate barrier or service gap by barangay. Avoid entering personal medical records.</p>
    <div id="wyNeedModalFormMount"></div>
  </dialog>

  <!-- Modal: Record Existing Service -->
  <dialog id="wyServiceModal" class="wy-modal" aria-labelledby="wyServiceModalTitle">
    <div class="wy-modal-head">
      <div>
        <span class="wy-kicker">Baseline Supply</span>
        <h2 id="wyServiceModalTitle">Record an Existing Health or Wellness Service</h2>
      </div>
      <button type="button" class="wy-modal-close" data-close-modal="wyServiceModal" aria-label="Close modal">×</button>
    </div>
    <p class="wy-muted">Submitted services remain provisional until reviewed by authorized CHO or LEBDO staff.</p>
    <div id="wyServiceModalFormMount"></div>
  </dialog>
</main>

<script type="module" src="<?= htmlspecialchars($context['assetBase'], ENT_QUOTES, 'UTF-8') ?>/js/wellness.js<?= sfc_asset_version('js/wellness.js') ?>"></script>
<?php sfc_render_footer($context); ?>
