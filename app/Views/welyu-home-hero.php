<?php
declare(strict_types=1);
// Presentation only. Reuse the existing catalogue destination and actual count.
?>
  <section class="welyu-hero" id="locus-react-hero" aria-labelledby="welyuHeroTitle">
    <img class="welyu-hero-photo" src="<?= $e($context['assetBase']) ?>/images/landing-city.jpg" width="1920" height="1080" alt="Aerial view of San Fernando City's neighborhoods, trees, and local businesses." fetchpriority="high" decoding="async">
    <div class="welyu-hero-wash" aria-hidden="true"></div>
    <div class="welyu-hero-grain" aria-hidden="true"></div>

    <div class="welyu-hero-topline">
      <span class="welyu-hero-top-badge">
        <img src="<?= $e($context['assetBase']) ?>/images/welyu-shield.png" alt="" width="20" height="20" class="welyu-topline-mark" aria-hidden="true">
        <i>01</i>
        <span>San Fernando &middot; La Union, Philippines</span>
      </span>
      <span class="welyu-hero-top-loc">Wellness &amp; Land Intelligence</span>
    </div>

    <div class="welyu-hero-copy">
      <p class="welyu-eyebrow">A thoughtful place to begin</p>
      <h1 id="welyuHeroTitle">Better places.<br><span class="welyu-headline-em">Healthier possibilities.</span></h1>
      <p class="welyu-lead">See the place. Understand the context.<br>Make room for what your community needs.</p>
      <div class="welyu-hero-actions">
        <a class="welyu-pill welyu-pill-lime" href="#properties"><span>Explore possibilities</span><span class="welyu-arrow" aria-hidden="true">&nearr;</span></a>
        <a class="welyu-pill welyu-pill-ghost" href="#at-a-glance"><span>The City at a Glance</span><span class="welyu-arrow-down" aria-hidden="true">&darr;</span></a>
      </div>
    </div>

    <a class="welyu-scene-pin welyu-scene-pin-community" href="#at-a-glance"><span class="welyu-pin-ring" aria-hidden="true">+</span><span>Community</span></a>
    <a class="welyu-scene-pin welyu-scene-pin-context" href="#why-invest"><span class="welyu-pin-ring" aria-hidden="true">+</span><span>Local context</span></a>

    <div class="welyu-hero-bottom">
      <a class="welyu-availability" href="<?= $e(sfc_path($cataloguePath)) ?>">
        <span class="welyu-availability-count"><?= isset($heroListingCount) ? $e(number_format($heroListingCount)) : '—' ?><small>active listings</small></span>
        <span class="welyu-availability-text"><strong>A place for your next idea.</strong><small>Explore the property collection</small></span>
        <span class="welyu-round-arrow" aria-hidden="true">&nearr;</span>
      </a>

      <div class="welyu-hero-stats-strip" aria-label="Key City Highlights">
        <div class="welyu-stat-item">
          <strong>59</strong>
          <span>Barangays</span>
        </div>
        <div class="welyu-stat-item">
          <strong>100%</strong>
          <span>CLUP Screened</span>
        </div>
        <div class="welyu-stat-item">
          <strong>6 Yrs</strong>
          <span>Tax Holiday</span>
        </div>
      </div>

      <a class="welyu-hero-scroll" href="#at-a-glance"><span>Rooted in community</span><svg viewBox="0 0 48 48" width="46" height="46" fill="none" aria-hidden="true"><path d="M24 4v40M4 24h40M10 10l28 28M10 38l28-28" stroke="currentColor" stroke-width="6"/></svg><span>Inspired by possibility</span></a>
      <a class="welyu-hero-note" href="#about"><span class="welyu-note-dot" aria-hidden="true"></span><span>More than a location.<br><strong>A considered decision.</strong></span><span aria-hidden="true">&nearr;</span></a>
    </div>
  </section>

  <div class="welyu-discovery-strip">
    <span class="welyu-strip-place"><span class="welyu-mini-flower" aria-hidden="true">&#10035;</span> Made for San Fernando.<br><small>Built around its possibilities.</small></span>
    <a class="welyu-strip-link" href="#why-invest">Get to know the place <span aria-hidden="true">&nearr;</span></a>
    <span class="welyu-strip-note">Community &middot; Context &middot; Considered investment</span>
  </div>
