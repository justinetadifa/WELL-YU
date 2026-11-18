(() => {
  'use strict';
  const root = document.getElementById('wellnessDiscovery');
  if (!root) return;
  const config = window.SFC_APP_CONFIG || {};
  const path = route => `${config.basePath || ''}/${route}`;
  const escape = value => String(value ?? '').replace(/[&<>"']/g, char => ({'&':'&amp;', '<':'&lt;', '>':'&gt;', '"':'&quot;', "'":'&#39;'}[char]));
  const money = value => value == null || value === '' || !Number.isFinite(Number(value)) ? 'Price on request' : new Intl.NumberFormat('en-PH', {style:'currency', currency:'PHP', maximumFractionDigits:0}).format(Number(value));
  const safeLink = value => { try { const url = new URL(value); return ['https:', 'http:'].includes(url.protocol) ? url.href : ''; } catch { return ''; } };
  const imagePath = value => {
    if (safeLink(value)) return safeLink(value);
    return typeof value === 'string' && /^assets\/[a-z0-9/_ .%()-]+$/i.test(value) ? path(value) : path('assets/images/sfcView.png');
  };
  const icon = category => {
    const shapes = {
      hospital: '<path d="M9 3h6v6h6v6h-6v6H9v-6H3V9h6z"/>',
      rehab: '<circle cx="13" cy="4" r="2"/><path d="m7 11 5-4 4 3h4M12 7l-2 8-5 5m5-5 6 5M3 9h4"/>',
      pharmacy: '<path d="m9 4 11 11a4.2 4.2 0 0 1-6 6L3 10a4.2 4.2 0 0 1 6-6Zm-3 9 6-6"/>',
      fitness: '<path d="M6 5v14M3 8v8M18 5v14M21 8v8M6 12h12"/>',
      nutrition: '<path d="M20 3C9 3 4 7 5 13c1 6 13 6 15-10ZM4 21 15 10M8 17l-1-5m4 1h5"/>',
      mental_wellbeing: '<path d="M21 11a8 8 0 0 1-8 8H8l-5 3 1-6a8 8 0 1 1 17-5Z"/><path d="M8 10h8M8 14h5"/>',
      movement: '<circle cx="13" cy="4" r="2"/><path d="m6 12 5-4 5 3 4-1M11 8l-1 8-5 5m5-5 6 5"/>',
      preventive_care: '<path d="m12 3 8 4v5c0 5-8 9-8 9s-8-4-8-9V7l8-4Z"/><path d="M12 7v9m-4-5h8"/>',
      caregiver: '<circle cx="9" cy="7" r="3"/><path d="M3 21v-3a6 6 0 0 1 9-5m1 1a2.5 2.5 0 0 1 4-1 2.5 2.5 0 0 1 4 3l-4 5-4-5a2.5 2.5 0 0 1 0-2Z"/>',
      dialysis: '<path d="M12 2S4 10 4 15a8 8 0 0 0 16 0c0-5-8-13-8-13Z"/><path d="M8 16a4 4 0 0 0 4 4"/>',
      park: '<path d="m12 2-6 8h3l-5 7h6v5h4v-5h6l-5-7h3l-6-8Z"/>',
      emergency: '<path d="M3 7h11v12H3V7Zm11 4h4l3 4v4h-7V11ZM6 4h5M8 9v6m-3-3h6"/><circle cx="7" cy="19" r="2"/><circle cx="18" cy="19" r="2"/>',
      default: '<path d="M20.8 4.6a5.5 5.5 0 0 0-7.8 0L12 5.7l-1.1-1.1a5.5 5.5 0 0 0-7.8 7.8L12 21l8.8-8.6a5.5 5.5 0 0 0 0-7.8Z"/><path d="M4 12h4l2-4 4 8 2-4h4"/>'
    };
    const aliases = {rehabilitation:'rehab', fitness_centre:'fitness', mental_health:'mental_wellbeing', other:'caregiver', health_station:'hospital'};
    category = aliases[category] || category;
    return `<svg viewBox="0 0 24 24" width="24" height="24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">${shapes[category] || shapes.default}</svg>`;
  };
  const status = root.querySelector('[data-discovery-status]');
  const tabs = [...root.querySelectorAll('[data-discovery-tab]')];
  let snapshot = null;
  let loading = false;
  let reloadQueued = false;

  function selectTab(name, focus = false) {
    tabs.forEach(tab => {
      const selected = tab.dataset.discoveryTab === name;
      tab.setAttribute('aria-selected', String(selected));
      tab.tabIndex = selected ? 0 : -1;
      if (selected && focus) tab.focus();
    });
    root.querySelectorAll('[data-discovery-panel]').forEach(panel => { panel.hidden = panel.dataset.discoveryPanel !== name; });
  }
  root.querySelector('[role=tablist]').addEventListener('click', event => {
    const tab = event.target.closest('[data-discovery-tab]');
    if (tab) selectTab(tab.dataset.discoveryTab);
  });
  root.querySelector('[role=tablist]').addEventListener('keydown', event => {
    const current = tabs.indexOf(event.target.closest('[data-discovery-tab]'));
    if (current < 0 || !['ArrowLeft', 'ArrowRight', 'Home', 'End'].includes(event.key)) return;
    event.preventDefault();
    const next = event.key === 'Home' ? 0 : event.key === 'End' ? tabs.length - 1 : (current + (event.key === 'ArrowRight' ? 1 : -1) + tabs.length) % tabs.length;
    selectTab(tabs[next].dataset.discoveryTab, true);
  });

  function askingPrice(property) {
    const lease = `${money(property.leasePrice)} / ${property.leasePriceUnit === 'sqm' ? 'm² / ' : ''}${property.leasePeriod || 'month'}`;
    if (property.listingPurpose === 'lease') return lease;
    if (property.listingPurpose === 'sale_or_lease') return `${money(property.salePrice ?? property.price)} · lease available`;
    return money(property.salePrice ?? property.price);
  }
  function renderOpportunities() {
    const opportunities = snapshot.opportunities || [];
    const target = root.querySelector('[data-opportunity-list]');
    target.innerHTML = opportunities.length ? opportunities.slice(0, 3).map((property, index) => {
      const distance = Number(property.nearest_distance_m);
      const distanceLabel = Number.isFinite(distance) ? (distance < 1000 ? `${Math.round(distance)} m` : `${(distance / 1000).toFixed(1)} km`) : 'Distance unknown';
      const nearby = property.nearby_count ?? property.facility_count ?? 0;
      const link = path(`property-details.php?id=${encodeURIComponent(property.property_id)}`);
      return `<article class="pd-opportunity-card">
        <a class="pd-opportunity-image" href="${escape(link)}" tabindex="-1" aria-hidden="true"><img src="${escape(imagePath(property.image))}" alt="" loading="lazy"><span class="pd-rank">${String(index + 1).padStart(2, '0')} <span>Nearby candidate</span></span></a>
        <div class="pd-opportunity-body"><div class="pd-card-kicker">${escape(property.idea_title || 'Wellness business potential')}</div>
          <h3><a href="${escape(link)}">${escape(property.title)}</a></h3><p class="pd-location">${escape(property.barangay || 'Location to confirm')} · ${escape(property.category || 'Property')}</p>
          <div class="pd-opportunity-facts"><div class="pd-fact"><strong>${escape(distanceLabel)}</strong><span>to nearest care anchor</span></div><div class="pd-fact"><strong>${escape(nearby)} facilities</strong><span>within 2 km</span></div></div>
          <p class="pd-directory-note">Near ${escape(property.nearest_facility?.name || 'recorded care facilities')}. Approximate straight-line distance.</p>
          <div class="pd-card-foot"><div><span class="pd-card-kicker">${property.listingPurpose === 'lease' ? 'For lease' : property.listingPurpose === 'sale_or_lease' ? 'For sale or lease' : 'Asking price'}</span><strong class="pd-price">${escape(askingPrice(property))}</strong></div><a class="pd-card-open" href="${escape(link)}" aria-label="View ${escape(property.title)}"><span aria-hidden="true">↗</span></a></div>
        </div></article>`;
    }).join('') : `<div class="pd-empty"><h3>No nearby candidates to display yet.</h3><p>Available, approved listings with recorded coordinates and a care anchor within 2 km will appear here. ${Number(snapshot.missing_coordinates_count) > 0 ? `${escape(snapshot.missing_coordinates_count)} available listings still need map coordinates.` : 'Review the portfolio below or add a property to begin.'}</p><a class="pd-section-link" href="${escape(path('admin-properties.php#propertyPortfolio'))}">View property portfolio ↗</a></div>`;
    target.querySelectorAll('img').forEach(img => img.addEventListener('error', () => { img.src = path('assets/images/sfcView.png'); }, {once:true}));
    root.querySelector('[data-discovery-method]').textContent = `${snapshot.methodology || 'Available approved listings are ordered by proximity to recorded care anchors.'} ${snapshot.notice || ''}`;
  }
  function renderFacilities() {
    const query = root.querySelector('[data-facility-search]').value.trim().toLocaleLowerCase();
    const category = root.querySelector('[data-facility-category]').value;
    const facilities = (snapshot?.facilities || []).filter(facility => (!category || facility.category === category) && `${facility.name} ${facility.barangay} ${facility.address} ${facility.category_label}`.toLocaleLowerCase().includes(query));
    root.querySelector('[data-facility-count]').textContent = `${facilities.length} of ${snapshot?.facilities?.length || 0} recorded facilities${category || query ? ' match your filters' : ' in San Fernando'}.`;
    root.querySelector('[data-facility-list]').innerHTML = facilities.length ? facilities.map(facility => {
      const source = safeLink(facility.source_url);
      return `<article class="pd-facility"><div class="pd-facility-icon">${icon(facility.category)}</div><div class="pd-facility-copy"><span class="pd-tag">${escape(facility.category_label)}</span><h3>${escape(facility.name)}</h3><p>${escape(facility.address || facility.barangay || 'Address to confirm')}</p><div class="pd-facility-source">${escape(facility.evidence_label || 'Recorded in local inventory')}${source ? ` · <a href="${escape(source)}" target="_blank" rel="noopener noreferrer">Official source ↗</a>` : ''}</div></div></article>`;
    }).join('') : '<div class="pd-empty"><h3>No matching facilities.</h3><p>Try a different name, barangay, or facility type.</p><button class="pd-button is-secondary" type="button" data-clear-facilities>Clear filters</button></div>';
  }
  function renderIdeas() {
    root.querySelector('[data-business-ideas]').innerHTML = (snapshot.ideas || []).map((idea, index) => `<article class="pd-idea-card"><div class="pd-idea-icon">${icon(idea.category)}</div><div class="pd-card-kicker">CONCEPT ${String(index + 1).padStart(2, '0')}</div><h3>${escape(idea.title)}</h3><p>${escape(idea.description)}</p><div class="pd-idea-context">${escape(idea.fit)}</div><button type="button" class="pd-idea-link" data-open-concept="${escape(idea.id)}">Explore this idea <span aria-hidden="true">↗</span></button></article>`).join('') || '<p class="pd-empty">Business concepts are being prepared.</p>';
  }
  async function load() {
    if (loading) return;
    loading = true;
    root.setAttribute('aria-busy', 'true');
    status.hidden = false;
    status.textContent = 'Finding local wellness opportunities…';
    try {
      const response = await fetch(`${config.apiBase || path('api')}/property-wellness.php`, {credentials:'same-origin', headers:{Accept:'application/json'}});
      const payload = await response.json();
      if (!response.ok) throw new Error(payload.error || 'Local wellness information could not be loaded.');
      snapshot = payload;
      const categories = payload.categories || [];
      const selectedCategory = root.querySelector('[data-facility-category]').value;
      root.querySelector('[data-facility-category]').innerHTML = '<option value="">All facility types</option>' + categories.map(category => `<option value="${escape(category.id)}">${escape(category.label)} (${Number(category.count) || 0})</option>`).join('');
      if (categories.some(category => category.id === selectedCategory)) root.querySelector('[data-facility-category]').value = selectedCategory;
      renderOpportunities(); renderFacilities(); renderIdeas();
      const counts = {investments:Math.min(3, payload.opportunities?.length || 0), facilities:payload.facilities?.length || 0, ideas:payload.ideas?.length || 0};
      root.querySelectorAll('[data-discovery-count]').forEach(node => { node.textContent = String(counts[node.dataset.discoveryCount]); });
      root.querySelector('[data-inventory-note]').textContent = `Local inventory dated ${payload.inventory_date || 'date not recorded'}. These are recorded facilities, not a live operating-status feed. Confirm services, hours, and site details with the provider. ${payload.coordinate_notice || ''}`;
      status.textContent = `${counts.investments} nearby investment candidates · ${counts.facilities} recorded health & wellness facilities · ${counts.ideas} business concepts`;
    } catch (error) {
      status.innerHTML = `${escape(error.message || 'Local wellness information could not be loaded.')} <button type="button" class="pd-idea-link" data-retry-discovery>Try again ↻</button>`;
    } finally {
      loading = false; root.setAttribute('aria-busy', 'false');
      if (reloadQueued) { reloadQueued = false; load(); }
    }
  }
  root.querySelector('[data-facility-search]').addEventListener('input', renderFacilities);
  root.querySelector('[data-facility-category]').addEventListener('change', renderFacilities);
  const dialog = document.getElementById('pdConceptDialog');
  root.addEventListener('click', event => {
    if (event.target.closest('[data-retry-discovery]')) load();
    if (event.target.closest('[data-clear-facilities]')) {
      root.querySelector('[data-facility-search]').value = '';
      root.querySelector('[data-facility-category]').value = '';
      renderFacilities(); root.querySelector('[data-facility-search]').focus();
    }
    const button = event.target.closest('[data-open-concept]');
    const idea = button && snapshot?.ideas?.find(item => String(item.id) === button.dataset.openConcept);
    if (!idea || !dialog) return;
    dialog.querySelector('#pdConceptTitle').textContent = idea.title;
    dialog.querySelector('[data-concept-description]').textContent = idea.description;
    dialog.querySelector('[data-concept-fit]').textContent = idea.fit;
    dialog.querySelector('[data-concept-validation]').textContent = idea.validation;
    dialog.showModal();
  });
  dialog?.addEventListener('click', event => { if (event.target.closest('[data-close-concept]')) dialog.close(); });
  document.addEventListener('sfc:properties-refreshed', () => { if (loading) reloadQueued = true; else load(); });
  load();
})();
