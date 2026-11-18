<?php
declare(strict_types=1);
require __DIR__ . '/app/Support/web.php';
require_once __DIR__ . '/app/Support/WellnessInvestmentHub.php';

use App\Support\WellnessInvestmentHub;

$context = sfc_web_context();
$e = static fn (mixed $value): string => htmlspecialchars((string) ($value ?? ''), ENT_QUOTES, 'UTF-8');
$hub = WellnessInvestmentHub::snapshot([]);
$listingsUnavailable = false;
try {
    // Public visibility is applied even when the viewer is a city administrator.
    $hub = WellnessInvestmentHub::snapshot(sfc_app_container()['properties']->all(null));
} catch (Throwable $error) {
    $listingsUnavailable = true;
    error_log('[wELYU investment hub] Public listings unavailable: ' . get_class($error));
}
$icon = static function (string $category): string {
    $paths = match ($category) {
        'hospital', 'health_station', 'preventive_care' => '<path d="M9 3h6v6h6v6h-6v6H9v-6H3V9h6z"/>',
        'nutrition' => '<path d="M20 3C9 3 4 7 5 13c1 6 13 6 15-10ZM4 21 15 10M8 17l-1-5m4 1h5"/>',
        'rehabilitation', 'rehab', 'movement' => '<circle cx="13" cy="4" r="2"/><path d="m6 12 5-4 5 3 4-1M11 8l-1 8-5 5m5-5 6 5"/>',
        'mental_health', 'mental_wellbeing' => '<path d="M21 11a8 8 0 0 1-8 8H8l-5 3 1-6a8 8 0 1 1 17-5Z"/><path d="M8 10h8M8 14h5"/>',
        'dialysis' => '<path d="M12 2S4 10 4 15a8 8 0 0 0 16 0c0-5-8-13-8-13Z"/><path d="M8 16a4 4 0 0 0 4 4"/>',
        'pharmacy' => '<path d="m9 4 11 11a4.2 4.2 0 0 1-6 6L3 10a4.2 4.2 0 0 1 6-6Zm-3 9 6-6"/>',
        'fitness_centre' => '<path d="M6 5v14M3 8v8M18 5v14M21 8v8M6 12h12"/>',
        'park' => '<path d="m12 2-6 8h3l-5 7h6v5h4v-5h6l-5-7h3l-6-8Z"/>',
        default => '<path d="M20.8 4.6a5.5 5.5 0 0 0-7.8 0L12 5.7l-1.1-1.1a5.5 5.5 0 0 0-7.8 7.8L12 21l8.8-8.6a5.5 5.5 0 0 0 0-7.8Z"/><path d="M4 12h4l2-4 4 8 2-4h4"/>',
    };
    return '<svg viewBox="0 0 24 24" width="24" height="24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' . $paths . '</svg>';
};
$money = static fn (mixed $value): string => $value === null || $value === '' || !is_numeric($value) ? 'Price on request' : '₱' . number_format((float) $value, 0);
$price = static function (array $p) use ($money): string {
    if (($p['listingPurpose'] ?? '') === 'lease') {
        return $money($p['leasePrice'] ?? null) . ' / ' . (($p['leasePriceUnit'] ?? '') === 'sqm' ? 'm² / ' : '') . ($p['leasePeriod'] ?? 'month');
    }
    return $money($p['salePrice'] ?? $p['price'] ?? null);
};
$image = static function (string $source): string {
    if (preg_match('#^https?://#i', $source)) { return $source; }
    return sfc_path('/' . (str_starts_with($source, 'assets/') ? $source : 'assets/images/sfcView.png'));
};
$facilitySearch = static fn (array $f): string => implode(' ', [$f['name'] ?? '', $f['address'] ?? '', $f['barangay'] ?? '', $f['category'] ?? '', implode(' ', $f['services'] ?? [])]);
$sourceDate = $hub['source_metadata']['checked_on'] ?? 'not recorded';
sfc_render_head('Wellness investments | San Fernando, La Union', $context, ['page' => 'city-investments', 'role' => $context['user']['role'] ?? 'guest']);
?>
<link rel="stylesheet" href="<?= $e($context['assetBase']) ?>/css/wellness-investments.css<?= sfc_asset_version('css/wellness-investments.css') ?>">
<?php sfc_render_header($context, 'wellness-investments'); ?>
<main class="city-container wi-page" id="wellnessInvestmentHub">
  <div class="wi-breadcrumb"><a href="<?= $e(sfc_path('/index.php')) ?>">wELYU</a><span aria-hidden="true">/</span><span>Wellness investments</span><span class="wi-pill">San Fernando, La Union</span></div>
  <section class="wi-hero" aria-labelledby="wiTitle">
    <div class="wi-hero-copy"><p class="wi-eyebrow">LOCAL CARE. NEW POSSIBILITIES.</p><h1 id="wiTitle">Wellness with purpose.<br><span>Places with potential.</span></h1><p>Meet the city’s established care providers, explore nearby investment sites, and discover health and wellness businesses to build.</p><div class="wi-hero-actions"><a class="wi-btn is-primary" href="<?= $e(sfc_path('/wellness-investments.php#facilities')) ?>">Explore city facilities <span aria-hidden="true">↗</span></a><a class="wi-btn is-secondary" href="<?= $e(sfc_path('/wellness-investments.php#build')) ?>">Find a business idea <span aria-hidden="true">↓</span></a></div></div>
    <div class="wi-hero-visual"><img src="<?= $e($context['assetBase']) ?>/images/admin-city.jpg" alt="San Fernando City and its coastline at sunset" width="1600" height="900" fetchpriority="high"><div class="wi-hero-caption"><span>WELLNESS, ROOTED IN PLACE</span><strong>A healthier future.<br>Built around our city.</strong></div></div>
  </section>
  <div class="wi-stats" aria-label="Wellness discovery overview">
    <article><span>Established care anchors</span><strong><?= count($hub['featured_facilities'] ?? []) ?></strong><small>Official provider sources linked</small></article>
    <article><span>Recorded local facilities</span><strong><?= (int) $hub['facility_count'] ?></strong><small>Across <?= count($hub['categories']) ?> health &amp; wellness categories</small></article>
    <article><span>Business concepts to explore</span><strong><?= count($hub['ideas']) ?></strong><small>Open, improve, or partner</small></article>
  </div>
  <nav class="wi-section-nav" aria-label="Wellness investment sections"><a href="<?= $e(sfc_path('/wellness-investments.php#facilities')) ?>" aria-current="location" data-hub-section="facilities"><span>01</span> City facilities</a><a href="<?= $e(sfc_path('/wellness-investments.php#invest')) ?>" data-hub-section="invest"><span>02</span> Where to invest</a><a href="<?= $e(sfc_path('/wellness-investments.php#build')) ?>" data-hub-section="build"><span>03</span> What to build</a></nav>

  <section class="wi-section" id="facilities" aria-labelledby="wiFacilitiesTitle">
    <div class="wi-heading"><div><p class="wi-eyebrow">01 / THE CITY’S CARE ECOSYSTEM</p><h2 id="wiFacilitiesTitle">Know the places already caring for our city.</h2><p>Established facilities with official provider references. Explore their services and possible complementary partnerships.</p></div><a class="wi-heading-link" href="<?= $e(sfc_path('/wellness-investments.php#wiSources')) ?>">About the sources ↗</a></div>
    <p class="wi-note">Featured alphabetically using linked official sources. Contact each provider to confirm services and partnership availability.</p>
    <div class="wi-filters" role="search" aria-label="Find a health or wellness facility"><label><span class="visually-hidden">Search name, barangay, or service</span><input type="search" data-hub-search placeholder="Search a facility, barangay, or service…"></label><label><span class="visually-hidden">Facility category</span><select data-hub-category><option value="">All facility types</option><?php foreach ($hub['categories'] as $category): ?><option value="<?= $e($category['id']) ?>"><?= $e($category['label']) ?></option><?php endforeach; ?></select></label></div>
    <p class="wi-filter-count" data-hub-filter-count aria-live="polite"><?= count($hub['featured_facilities'] ?? []) ?> featured facilities · <?= (int) $hub['facility_count'] ?> inventory records</p>
    <div class="wi-featured-grid">
      <?php foreach (($hub['featured_facilities'] ?? []) as $facility): ?>
      <article class="wi-facility-card" data-hub-featured data-category="<?= $e($facility['category']) ?>" data-search="<?= $e($facilitySearch($facility)) ?>">
        <div class="wi-card-top"><div class="wi-icon"><?= $icon($facility['category']) ?></div><span class="wi-pill"><?= $facility['sector'] === 'public' ? 'Public care anchor' : ($facility['sector'] === 'private' ? 'Private provider' : 'Provider') ?></span></div>
        <p class="wi-card-kicker">OFFICIAL PROVIDER REFERENCE</p><h3><?= $e($facility['name']) ?></h3><p class="wi-address"><?= $e($facility['address']) ?></p>
        <div class="wi-services"><?php foreach (($facility['services'] ?? []) as $service): ?><span><?= $e($service) ?></span><?php endforeach; ?></div>
        <p class="wi-source"><a href="<?= $e($facility['source_url']) ?>" target="_blank" rel="noopener noreferrer" title="<?= $e($facility['source_title']) ?>">Provider reference ↗</a> · <a href="<?= $e($facility['service_source_url']) ?>" target="_blank" rel="noopener noreferrer" title="<?= $e($facility['service_source_title']) ?>">Published services ↗</a></p>
        <div class="wi-card-actions"><button type="button" class="wi-btn is-secondary" data-invest-facility="<?= $e($facility['id']) ?>"><?= $e($facility['action_label'] ?? 'Explore partnership') ?> <span aria-hidden="true">↗</span></button></div>
      </article>
      <?php endforeach; ?>
    </div>
    <div class="wi-empty" data-no-featured hidden><h3>No featured facilities match.</h3><p>Try another search or check the full inventory below.</p><button type="button" class="wi-btn is-secondary" data-hub-clear>Clear filters</button></div>
    <details class="wi-directory" id="wiFullDirectory"><summary>Browse the full local inventory <span><?= (int) $hub['facility_count'] ?> recorded facilities</span></summary><p class="wi-note">Inventory dated <?= $e($hub['inventory_date']) ?>. These records are not a live operating-status feed. Confirm services and hours with the provider.</p><div class="wi-directory-list">
      <?php foreach ($hub['facilities'] as $facility): ?>
      <article class="wi-directory-item" data-hub-inventory data-category="<?= $e($facility['category']) ?>" data-search="<?= $e($facilitySearch($facility)) ?>"><div class="wi-icon"><?= $icon($facility['category']) ?></div><div><span class="wi-pill"><?= $e($facility['category_label']) ?></span><h3><?= $e($facility['name']) ?></h3><p><?= $e($facility['address'] ?: $facility['barangay']) ?></p><p class="wi-source"><?= $e($facility['evidence_label']) ?><?php if ($facility['source_url']): ?> · <a href="<?= $e($facility['source_url']) ?>" target="_blank" rel="noopener noreferrer">Official source ↗</a><?php endif; ?></p></div></article>
      <?php endforeach; ?>
    </div><p class="wi-empty" data-no-inventory hidden>No inventory records match. Try a different category or clear your search.</p></details>
  </section>

  <section class="wi-section" id="invest" aria-labelledby="wiInvestTitle">
    <div class="wi-heading"><div><p class="wi-eyebrow">02 / YOUR NEXT MOVE</p><h2 id="wiInvestTitle">Three ways to invest in better care.</h2><p>Explore a new location, improve an existing service, or discuss a partnership with a provider.</p></div><a class="wi-heading-link" href="<?= $e(sfc_path('/wellness.php#plan')) ?>">Open the planning workspace ↗</a></div>
    <div class="wi-pathways">
      <?php foreach (($hub['investment_modes'] ?? []) as $index => $mode): ?>
      <article class="wi-pathway"><p class="wi-card-kicker"><?= $e(str_pad((string) ($index + 1), 2, '0', STR_PAD_LEFT)) ?> / <?= $e(strtoupper($mode['id'])) ?></p><h3><?= $e($mode['title']) ?></h3><p><?= $e($mode['description']) ?></p><a href="<?= $e(sfc_path($mode['id'] === 'open' ? '/wellness-investments.php#wiSites' : '/wellness-investments.php#facilities')) ?>"><?= $e($mode['action_label']) ?> ↗</a></article>
      <?php endforeach; ?>
    </div>
    <div class="wi-invest-intro" id="wiSites"><h3>Nearby sites worth a closer look.</h3><p>Publicly available properties within 2 km of a recorded care anchor, ordered by proximity. Prices are property asking prices.</p></div>
    <div class="wi-site-grid">
      <?php foreach ($hub['opportunities'] as $index => $property): $propertyLink = sfc_path('/property-details.php?id=' . (int) $property['property_id']); ?>
      <article class="wi-site-card"><a class="wi-site-image" href="<?= $e($propertyLink) ?>" aria-hidden="true" tabindex="-1"><img src="<?= $e($image($property['image'])) ?>" alt="" loading="lazy" width="800" height="450"><span class="wi-rank"><?= $e(str_pad((string) ($index + 1), 2, '0', STR_PAD_LEFT)) ?> · NEARBY CANDIDATE</span></a><div class="wi-site-body"><p class="wi-card-kicker"><?= $e($property['idea_title']) ?></p><h3><a href="<?= $e($propertyLink) ?>"><?= $e($property['title']) ?></a></h3><p class="wi-address"><?= $e($property['barangay']) ?> · <?= $e($property['category']) ?></p><div class="wi-site-facts"><div><strong><?= $property['nearest_distance_m'] < 1000 ? (int) $property['nearest_distance_m'] . ' m' : number_format($property['nearest_distance_m'] / 1000, 1) . ' km' ?></strong><span>to nearest care anchor</span></div><div><strong><?= (int) $property['nearby_count'] ?> facilities</strong><span>within 2 km</span></div></div><p class="wi-note">Near <?= $e($property['nearest_facility']['name']) ?>. Approximate straight-line distance.</p><div class="wi-site-footer"><div><span><?= ($property['listingPurpose'] ?? '') === 'lease' ? 'FOR LEASE' : (($property['listingPurpose'] ?? '') === 'sale_or_lease' ? 'FOR SALE OR LEASE' : 'ASKING PRICE') ?></span><strong><?= $e($price($property)) ?></strong></div><a href="<?= $e($propertyLink) ?>" aria-label="View <?= $e($property['title']) ?>">↗</a></div></div></article>
      <?php endforeach; ?>
    </div>
    <?php if (!$hub['opportunities']): ?><div class="wi-empty"><h3><?= $listingsUnavailable ? 'Property listings are temporarily unavailable.' : 'No nearby investment sites match yet.' ?></h3><p><?= $listingsUnavailable ? 'The facility directory and business concepts are still available. Refresh to try the property listings again.' : $e($hub['empty_message']) ?></p><a class="wi-btn is-secondary" href="<?= $e(sfc_path($listingsUnavailable ? '/wellness-investments.php#invest' : '/property-explorer.php')) ?>"><?= $listingsUnavailable ? 'Try listings again ↻' : 'Explore all properties ↗' ?></a></div><?php endif; ?>
    <details class="wi-evidence-note"><summary>How the nearby site shortlist works</summary><p><?= $e($hub['methodology']) ?></p><p>Property proximity does not establish permitted use, demand, referral arrangements, or financial returns. Verify location, accessibility, zoning, staffing, and operating requirements with the responsible offices.</p></details>
  </section>

  <section class="wi-section" id="build" aria-labelledby="wiBuildTitle">
    <div class="wi-heading"><div><p class="wi-eyebrow">03 / WHAT COULD COME NEXT</p><h2 id="wiBuildTitle">Build something that helps people thrive.</h2><p>Six concepts to explore alongside the city’s care network. Each comes with a local context and practical next steps.</p></div></div>
    <p class="wi-note">Suggestions reflect the recorded care ecosystem. A service shortage or commercial demand has not been established; validate the need with residents and local providers.</p>
    <div class="wi-ideas-grid">
      <?php foreach ($hub['ideas'] as $index => $idea): ?>
      <article class="wi-idea-card"><div class="wi-icon"><?= $icon($idea['category']) ?></div><p class="wi-card-kicker">CONCEPT <?= $e(str_pad((string) ($index + 1), 2, '0', STR_PAD_LEFT)) ?></p><h3><?= $e($idea['title']) ?></h3><p><?= $e($idea['description']) ?></p><div class="wi-idea-evidence"><strong>Local context</strong><p><?= $e($idea['evidence_basis'] ?? $idea['fit']) ?></p></div><div class="wi-idea-actions"><button type="button" class="wi-btn is-secondary" data-hub-idea="<?= $e($idea['id']) ?>">Explore this concept <span aria-hidden="true">↗</span></button></div></article>
      <?php endforeach; ?>
    </div>
  </section>
  <details class="wi-evidence-note" id="wiSources"><summary>Sources, selection, and data dates</summary><p>Official provider references checked <?= $e($sourceDate) ?>. The broader inventory is dated <?= $e($hub['inventory_date']) ?>. Official sources confirm the cited identity, address, or services; the wider inventory contains locally recorded entries.</p><p>Featured facilities are shown alphabetically, without a revenue, quality-of-care, or investment-return ranking. No equity, franchise, fundraising, or facility acquisition offer has been verified. Contact the provider to establish whether any partnership is available.</p><p>Existing-site and new-build concepts require demand research, site due diligence, operator consent, and the applicable professional and city approvals. Public hospitals and government services are care anchors; a private ownership offer is not implied.</p></details>
  <section class="wi-final-banner"><div><p class="wi-eyebrow">FROM POSSIBILITY TO A PLAN</p><h2>Give a good idea a thoughtful next step.</h2><p>Compare opening, improving, and partnering in the wellness planning workspace.</p></div><a class="wi-btn is-primary" href="<?= $e(sfc_path('/wellness.php#plan')) ?>">Start planning <span aria-hidden="true">↗</span></a></section>
  <noscript><p class="wi-note">All facilities, sites, and business concepts are shown above. Enable JavaScript to use filters and open the concept checklists.</p></noscript>
</main>
<dialog class="wi-dialog" id="wiIdeaDialog" aria-labelledby="wiIdeaTitle"><button class="wi-dialog-close" type="button" data-hub-close aria-label="Close concept">×</button><p class="wi-eyebrow">BUSINESS CONCEPT / NEXT STEPS</p><h2 id="wiIdeaTitle"></h2><p data-idea-description></p><div class="wi-dialog-grid"><div><h3>Where it could fit</h3><p data-idea-fit></p></div><div><h3>Recorded local context</h3><p data-idea-evidence></p><p class="wi-source"><a data-idea-source target="_blank" rel="noopener noreferrer">Read the source ↗</a></p></div><div><h3>What to validate</h3><ol data-idea-steps></ol></div></div><div class="wi-dialog-actions"><a class="wi-btn is-primary" href="<?= $e(sfc_path('/wellness.php#plan')) ?>">Plan this concept ↗</a><button class="wi-btn is-secondary" type="button" data-hub-close>Close</button></div></dialog>
<dialog class="wi-dialog" id="wiFacilityDialog" aria-labelledby="wiFacilityTitle"><button class="wi-dialog-close" type="button" data-hub-close aria-label="Close facility opportunity">×</button><p class="wi-eyebrow">EXPLORE A FACILITY PARTNERSHIP</p><h2 id="wiFacilityTitle"></h2><p data-facility-address></p><div class="wi-dialog-grid"><div><h3>Opportunity status</h3><p data-facility-investment></p></div><div><h3>Start a conversation</h3><p>Ask the provider which services, spaces, or partnerships they are open to discussing. Confirm the authorized contact, operating needs, and proposed terms.</p></div><div><h3>Before committing</h3><p>Verify demand, financial assumptions, ownership or lease terms, required licenses, and operator consent. No funding or ownership offer is confirmed by this listing.</p></div></div><div class="wi-dialog-actions"><a class="wi-btn is-primary" data-provider-link target="_blank" rel="noopener noreferrer">Visit official provider ↗</a><a class="wi-btn is-secondary" href="<?= $e(sfc_path('/wellness.php#plan')) ?>">Plan a partnership ↗</a></div></dialog>
<script type="application/json" id="wellnessInvestmentData"><?= json_encode(['ideas' => $hub['ideas'], 'featured_facilities' => $hub['featured_facilities'] ?? []], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE) ?></script>
<script src="<?= $e($context['assetBase']) ?>/js/wellness-investments.js<?= sfc_asset_version('js/wellness-investments.js') ?>" defer></script>
<?php sfc_render_footer($context); ?>
