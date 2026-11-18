<?php
declare(strict_types=1);

require __DIR__ . '/app/Support/web.php';

$context = sfc_web_context();
sfc_render_head('City Pipeline | LOCUS-SF', $context, ['page' => 'city-pipeline', 'role' => $context['user']['role'] ?? 'guest']);
sfc_render_header($context, 'city-pipeline');
?>
<main class="page-shell pipeline-page-shell">
  <div class="site-shell" style="margin-bottom: 14px;">
    <p style="margin:0;"><a class="btn-shell btn-shell-secondary" href="<?= htmlspecialchars(sfc_path('/wellness.php#follow'), ENT_QUOTES, 'UTF-8') ?>">Wellness proposals &amp; follow-up</a></p>
  </div>
  <div class="site-shell pipeline-site-shell" id="cityPipelineRoot">
    <div class="loading-panel">Loading city pipeline signal atlas...</div>
  </div>
</main>
<?php sfc_render_footer($context); ?>
