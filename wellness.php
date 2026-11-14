<?php
declare(strict_types=1);
require __DIR__ . '/app/Support/web.php';
$context = sfc_web_context();
sfc_render_head('Wellness investment workspace | wELYU', $context, ['page' => 'city-wellness', 'role' => $context['user']['role'] ?? 'guest']);
sfc_render_header($context, 'wellness');
?>
<link rel="stylesheet" href="<?= htmlspecialchars($context['assetBase'], ENT_QUOTES, 'UTF-8') ?>/css/wellness.css<?= sfc_asset_version('css/wellness.css') ?>">
<main class="city-container wy-workspace" id="wellnessWorkspace" aria-labelledby="wellnessTitle">
  <header class="wy-intro"><div><p class="wy-kicker">Community &amp; location intelligence</p><h1 id="wellnessTitle">A thoughtful investment.<br><em>A healthier possibility.</em></h1><p>Start with a need, a service or a place. Bring the evidence together before deciding what comes next.</p></div><a class="city-button city-button-secondary" href="city-pipeline.php">City pipeline ↗</a></header>
  <div id="wellnessNotice" class="wy-notice" role="status" aria-live="polite">Loading the wellness workspace…</div>
  <nav class="wy-tabs" aria-label="Wellness workflow" id="wellnessTabs"></nav>
  <section id="wellnessView" aria-live="polite"><div class="wy-empty">Loading services and community context…</div></section>
  <noscript><p>This workspace needs JavaScript for its forms and comparisons. Your existing property pages remain available through the main navigation.</p></noscript>
</main>
<script defer src="<?= htmlspecialchars($context['assetBase'], ENT_QUOTES, 'UTF-8') ?>/js/wellness.js<?= sfc_asset_version('js/wellness.js') ?>"></script>
<?php sfc_render_footer($context); ?>
