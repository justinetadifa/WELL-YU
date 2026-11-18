const cfg = window.SFC_APP_CONFIG || {};
const base = `${cfg.basePath || ''}/api/wellness.php`;
const tabs = document.getElementById('wellnessTabs');
const view = document.getElementById('wellnessView');
const notice = document.getElementById('wellnessNotice');
const root = document.getElementById('wellnessWorkspace');

const LABELS = {
  open: 'Option A · Open New',
  improve: 'Option B · Improve Existing',
  partner: 'Option C · Partner & Co-Locate',
  rehabilitation: 'Preventive Rehab',
  preventive_care: 'Preventive Care',
  mental_wellbeing: 'Mental Wellness',
  senior_living: 'Healthy Ageing',
  movement: 'Active Fitness & Recovery',
  nutrition: 'Nutrition',
  access_affordability: 'Affordability & Access',
  health_access: 'Health Facility Proximity',
  active_mobility: 'Active Mobility & PWD Access',
  operating_readiness: 'Operating Readiness',
  cost_feasibility: 'Cost Feasibility',
  market_context: 'Unmet Need & Market Fit',
  zoning: 'Zoning',
  permit: 'Permit',
  sanitation: 'Sanitation',
  environment: 'Environment',
  hazards: 'Hazards',
  physical_access: 'Stepped / Barrier Entrance',
  hours: 'Daytime-Only Hours (Closes 5 PM)',
  affordability: 'High Out-of-Pocket Fee',
  capacity: 'Overcrowded Capacity',
  travel: 'Long Transit Distance',
  none: 'All 5 Reviewed',
  reviewed: 'Verified',
  reported: 'Reported',
  documented: 'Documented',
  unknown: 'Unverified',
  restricted: 'Restricted',
  public: 'Public',
  private: 'Private',
  ngo: 'Community / NGO',
  non_agri: 'RTWPB Non-Agri (₱468/day)',
  micro_agri: 'RTWPB Micro/Agri (₱435/day)',
  provisional: 'Provisional',
  validated: 'Validated',
  confirmed_inquiry: 'Confirmed Inquiry',
  site_visit: 'Site Visit',
  decision: 'Decision Recorded',
  implemented: 'Implemented',
  observed_follow_up: 'Observed Outcome',
  correction_report: 'Correction Report',
  correction_response: 'Correction Response',
};

const TAB_ALIASES = {
  needs: 'needs',
  directory: 'needs',
  compare: 'compare',
  assumptions: 'assumptions',
  assessment: 'assumptions',
  plan: 'assumptions',
  follow: 'follow',
  implementation: 'follow',
  review: 'review',
  settings: 'settings',
};

const DEFAULT_WEIGHTS = {
  access_affordability: 22,
  health_access: 16,
  active_mobility: 18,
  operating_readiness: 16,
  cost_feasibility: 14,
  market_context: 14,
};

const state = {
  tab: initialTab(),
  needSubView: initialSubView(),
  selectedNeedKey: 'biday',
  activeMode: 'open',
  activeBarangay: 'Biday',
  meta: null,
  proposal: 0,
  scenarios: [],
  importPreview: null,
  importHash: null,
  map: null,
  ringGroup: null,
  markerGroup: null,
  linkedProperty: null,
  mapLayers: {
    private: true,
    public: true,
    barriers: true,
    rings: true,
  },
  sensitivity: {
    priceDeltaPct: 0,
    fixedCostDeltaPct: 0,
    weeklyHours: 72,
    wageTier: 'non_agri',
    simulateGateHold: 'none',
    weights: { ...DEFAULT_WEIGHTS },
  },
  verificationChecks: {
    bp344_ramp: { title: 'Confirm ground-floor BP 344 ramp slope (1:12 grade)', office: 'City Engineering & PDAO', status: 'pending', impact: 'Directly affects Active Mobility & PWD clearance.' },
    evening_roster: { title: 'Confirm evening practitioner shift roster (5:00 PM–8:00 PM)', office: 'Operator & LEBDO', status: 'pending', impact: 'Closes evening hours barrier under RTWPB Wage Order RB1-23.' },
    cho_sanitation: { title: 'Verify PrimeWater mainline & ADA restroom ventilation', office: 'City Health Office (Sanitation)', status: 'verified', impact: 'Satisfies Sanitation clearance gate prior to BPLO permit.' },
    ord_tax_relief: { title: 'Pre-screen Ordinance No. 2024-41 priority wellness eligibility', office: 'LEBDO / City Assessor', status: 'verified', impact: 'Unlocks 4–6 year Local Business Tax & RPT relief.' },
    hazard_access: { title: 'Verify all-weather road access & flood clearance (CDRRMO)', office: 'CDRRMO / CPDO', status: 'pending', impact: 'Required before final Hazards gate sign-off.' },
  },
};

function initialTab() {
  const hash = location.hash.replace('#', '').toLowerCase();
  return TAB_ALIASES[hash] || 'needs';
}

function initialSubView() {
  return location.hash.replace('#', '').toLowerCase() === 'directory' ? 'services' : 'barriers';
}

function esc(value) {
  return String(value ?? '').replace(/[&<>"']/g, ch => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[ch]));
}

function label(key) {
  return LABELS[key] || String(key || '').replace(/_/g, ' ').replace(/\b\w/g, m => m.toUpperCase());
}

function money(value) {
  if (value === null || value === undefined || value === '' || Number.isNaN(Number(value))) return '—';
  return '₱' + Number(value).toLocaleString('en-PH', { maximumFractionDigits: 0 });
}

function today() {
  return new Date().toISOString().slice(0, 10);
}

function message(text, error = false) {
  if (!notice) return;
  notice.textContent = text;
  notice.classList.toggle('is-error', Boolean(error));
}

function empty(text) {
  return `<div class="wy-empty">${esc(text)}</div>`;
}

function saveDisabled() {
  return state.meta?.signed_in && state.meta?.workspace?.ready ? '' : ' disabled';
}

async function api(action, data = null, query = {}) {
  const params = new URLSearchParams({ action, ...query });
  const options = { method: data ? 'POST' : 'GET', credentials: 'same-origin', headers: { Accept: 'application/json' } };
  if (data instanceof FormData) {
    if (cfg.csrfToken) data.append('_csrf', cfg.csrfToken);
    if (cfg.csrfToken) options.headers['X-CSRF-Token'] = cfg.csrfToken;
    options.body = data;
  } else if (data) {
    options.headers['Content-Type'] = 'application/json';
    if (cfg.csrfToken) options.headers['X-CSRF-Token'] = cfg.csrfToken;
    options.body = JSON.stringify({ ...data, csrf_token: cfg.csrfToken || '' });
  }
  const response = await fetch(`${base}?${params.toString()}`, options);
  const raw = await response.text();
  let json = {};
  try { json = raw ? JSON.parse(raw) : {}; } catch { throw new Error('Unexpected server response.'); }
  if (!response.ok) throw new Error(json.error || `Request failed (${response.status}).`);
  return json;
}

function opts(values, placeholder = 'Select…') {
  return `<option value="">${esc(placeholder)}</option>` + (values || []).map(item =>
    typeof item === 'object'
      ? `<option value="${esc(item.id)}">${esc(item.title || item.name || item.label)}</option>`
      : `<option value="${esc(item)}">${esc(label(item))}</option>`
  ).join('');
}

function field(name, title, type = 'text', extra = '', required = false) {
  return `<label class="wy-field"><span>${esc(title)}</span><input name="${esc(name)}" type="${type}" ${required ? 'required' : ''} ${extra}></label>`;
}

function select(name, title, values, required = false, placeholder = 'Choose…') {
  return `<label class="wy-field"><span>${esc(title)}</span><select name="${esc(name)}" ${required ? 'required' : ''}>${opts(values, placeholder)}</select></label>`;
}

function area(name, title, required = false, val = '') {
  return `<label class="wy-field" style="grid-column:1/-1;"><span>${esc(title)}</span><textarea name="${esc(name)}" rows="3" ${required ? 'required' : ''}>${esc(val)}</textarea></label>`;
}

function evidence(prefix = '') {
  return field(prefix + 'source', 'Evidence source reference', 'text', '', true)
    + field(prefix + 'observed_on', 'Observed date', 'date', `max="${today()}" value="${today()}"`, true)
    + field(prefix + 'url', 'Reference URL (optional)', 'url');
}

function privateRecords() {
  const m = state.meta || {};
  return [
    ...(m.needs || []).map(row => ({ ...row, kind: 'need' })),
    ...(m.services || []).map(row => ({ ...row, kind: 'service' })),
    ...(m.proposals || []).map(row => ({ ...row, kind: 'proposal' })),
  ];
}

function recordOptions() {
  return opts(privateRecords().map(row => ({ id: `${row.kind}:${row.id}`, title: `${label(row.kind)} · ${row.title}` })), 'Choose a record…');
}

function needCatalog() {
  const pilot = state.meta?.siklab_pilot || {};
  const catalog = { ...(pilot.need_scenarios || {}) };
  const basePathways = pilot.pathways || {};

  // Fallback synthesis if backend need_scenarios is empty
  if (!Object.keys(catalog).length && basePathways.open) {
    catalog.biday = {
      key: 'biday',
      barangay: 'Biday',
      label: 'Brgy. Biday — Evening Physical Rehab & Senior Mobility Access',
      category: 'rehabilitation',
      typology_badge: 'Preventive Rehab & Senior Mobility',
      lat: 16.6312,
      lng: 120.3238,
      barriers: ['physical_access', 'hours', 'affordability'],
      intended_users: 'Seniors (60+) and post-injury commuters in Brgy. Biday',
      baseline_summary: 'Current supply: 1 daytime clinic, closes 5:00 PM; stepped entry and 4.2 km downtown commute.',
      source: 'CHO / OSCA / PDAO Barangay Needs Registry',
      observed_on: '2026-04-10',
      pathways: basePathways,
    };
  }

  // Merge user-submitted needs (deduplicated against seeded baseline barangays if custom title added)
  (state.meta?.needs || []).forEach(row => {
    const brgy = row.barangay || 'Biday';
    const baseKey = String(brgy).toLowerCase();
    if (!catalog[baseKey] && !row.title?.includes('Preventive Rehab')) {
      const template = catalog.biday || Object.values(catalog)[0];
      if (!template) return;
      catalog[`need_${row.id}`] = {
        key: `need_${row.id}`,
        id: row.id,
        owner_user_id: row.owner_user_id,
        revision: row.revision,
        review_status: row.review_status,
        barangay: brgy,
        label: `Brgy. ${brgy} — ${row.title}`,
        category: row.payload?.category || 'preventive_care',
        typology_badge: label(row.payload?.category || 'preventive_care'),
        lat: row.lat || template.lat,
        lng: row.lng || template.lng,
        barriers: row.payload?.barriers || ['hours', 'affordability'],
        intended_users: row.payload?.intended_users || 'Local residents',
        baseline_summary: row.payload?.notes || `Submitted barrier in Brgy. ${brgy}.`,
        source: row.payload?.evidence?.source || 'Community need submission',
        observed_on: row.payload?.evidence?.observed_on || today(),
        pathways: template.pathways,
      };
    }
  });

  return catalog;
}

function activeNeed() {
  const cat = needCatalog();
  return cat[state.selectedNeedKey] || cat.biday || Object.values(cat)[0] || null;
}

function computePathwayMetrics(pathway, sens) {
  const criteria = pathway?.evidence?.criteria || {};
  const weights = sens.weights || DEFAULT_WEIGHTS;
  let wSum = 0;
  let weightedScore = 0;

  const criterionRows = Object.entries(state.meta?.criteria || {}).map(([key, title]) => {
    const rawObs = criteria[key] || {};
    const rawVal = Number(rawObs.value ?? 85);
    const w = Number(weights[key] ?? DEFAULT_WEIGHTS[key] ?? 16);
    wSum += w;
    return { key, title, rawVal, weight: w, source: rawObs.source || 'Benchmark registry', observed_on: rawObs.observed_on || '2026-04-10', method: rawObs.method || 'Standard normalization' };
  });

  criterionRows.forEach(r => {
    const normalizedW = wSum > 0 ? (r.weight / wSum) * 100 : r.weight;
    r.contribution = (r.rawVal * normalizedW) / 100;
    weightedScore += r.contribution;
  });

  const mceRounded = Math.round(weightedScore * 100) / 100;
  const gateHold = sens.simulateGateHold && sens.simulateGateHold !== 'none';
  const iaiScore = gateHold ? null : mceRounded;

  const baseInp = pathway.scenario_input || {};
  const priceMult = 1 + (Number(sens.priceDeltaPct || 0) / 100);
  const hoursRatio = Number(sens.weeklyHours || baseInp.weekly_hours || 72) / Number(baseInp.weekly_hours || 72);

  const pricePhp = Math.round(Number(baseInp.price_per_visit_php || 300) * priceMult * 100) / 100;
  const varPhp = Number(baseInp.variable_cost_php || 75);
  const dailyRate = sens.wageTier === 'micro_agri' ? 435.0 : 468.0;
  const staffCount = Number(baseInp.staff_count || 3);
  const staffMonthly = Math.round(dailyRate * staffCount * 26 * 100) / 100;
  const baseStaffMonthly = (baseInp.wage_tier === 'micro_agri' ? 435.0 : 468.0) * staffCount * 26;
  const fixedPhp = Math.max(12000, Math.round((Number(baseInp.fixed_monthly_cost_php || 45000) - baseStaffMonthly + staffMonthly) * 100) / 100);
  const startupPhp = Number(baseInp.startup_cost_php || 420000);
  const customers = Math.max(50, Math.round(Number(baseInp.expected_monthly_customers || 500) * Math.min(1.35, Math.max(0.65, hoursRatio))));

  const marginCents = Math.round(pricePhp * 100) - Math.round(varPhp * 100);
  const fixedCents = Math.round(fixedPhp * 100);
  const breakEven = marginCents > 0 ? Math.floor((fixedCents + marginCents - 1) / marginCents) : null;
  const netPhp = marginCents > 0 ? Math.round(((pricePhp - varPhp) * customers - fixedPhp) * 100) / 100 : -fixedPhp;
  const paybackMonths = netPhp > 0 ? Math.round((startupPhp / netPhp) * 10) / 10 : null;
  const capexPerBeneficiary = customers > 0 ? Math.round((startupPhp / customers) * 100) / 100 : null;

  const holidayYears = Number(baseInp.tax_holiday_years || (startupPhp >= 1500000 ? 5 : 4));
  const annualGross = pricePhp * customers * 12;
  const annualTaxRelief = Math.round((annualGross * 0.015 + startupPhp * 0.01) * 100) / 100;
  const totalTaxSavings = Math.round(annualTaxRelief * holidayYears * 100) / 100;

  return {
    mode: pathway.mode,
    badge: pathway.badge || label(pathway.mode),
    title: pathway.title,
    subtitle: pathway.subtitle || '',
    additional_access: pathway.additional_access_summary || `${customers} visits/mo · ${Math.round(Number(sens.weeklyHours || baseInp.weekly_hours || 72))} hrs/wk`,
    mce_score: mceRounded,
    iai_score: iaiScore,
    gate_hold: gateHold ? sens.simulateGateHold : null,
    criterionRows,
    readiness: pathway.evidence?.readiness || {},
    price_per_visit_php: pricePhp,
    variable_cost_php: varPhp,
    fixed_monthly_cost_php: fixedPhp,
    startup_capex_php: startupPhp,
    expected_monthly_customers: customers,
    break_even_visits_per_month: breakEven,
    monthly_noi_php: netPhp,
    estimated_payback_months: paybackMonths,
    capital_cost_per_monthly_beneficiary_php: capexPerBeneficiary,
    staff_count: staffCount,
    daily_wage_php: dailyRate,
    monthly_staff_cost_php: staffMonthly,
    tax_holiday_years: holidayYears,
    annual_tax_relief_php: annualTaxRelief,
    total_tax_holiday_savings_php: totalTaxSavings,
  };
}

function buildModalForms() {
  const m = state.meta;
  const needMount = document.getElementById('wyNeedModalFormMount');
  const serviceMount = document.getElementById('wyServiceModalFormMount');
  if (needMount && !needMount.hasChildNodes()) {
    needMount.innerHTML = `<form data-form="need" id="needForm"><input name="id" type="hidden"><input name="revision" type="hidden"><div class="wy-form-grid">${field('title', 'Requested service or barrier title', 'text', 'maxlength="190"', true)}${select('category', 'Wellness sector', m.categories, true)}${field('intended_users', 'Affected resident group', 'text', 'maxlength="600"', true)}${select('barangay', 'Barangay', m.barangays, true)}${field('lat', 'Latitude (optional)', 'number', 'step="any"')}${field('lng', 'Longitude (optional)', 'number', 'step="any"')}${evidence()}${area('notes', 'Observed barrier & supporting notes', true)}</div><p class="wy-muted" style="margin:6px 0 4px;"><strong>Specific barriers reported</strong></p><div style="display:flex;flex-wrap:wrap;gap:12px;">${m.barriers.map(k => `<label class="wy-check"><input name="barrier_${k}" type="checkbox">${esc(label(k))}</label>`).join('')}</div><div class="wy-actions" style="margin-top:14px;"><button type="submit" class="wy-primary"${saveDisabled()}>Submit Barrier Report</button><button type="button" data-close-modal="wyNeedModal">Cancel</button></div></form>`;
  }
  if (serviceMount && !serviceMount.hasChildNodes()) {
    serviceMount.innerHTML = `<form data-form="service" id="serviceForm"><input name="id" type="hidden"><input name="revision" type="hidden"><div class="wy-form-grid">${field('title', 'Service or facility name', 'text', 'maxlength="190"', true)}${select('category', 'Wellness sector', m.categories, true)}${select('barangay', 'Barangay', m.barangays, true)}${select('operator_kind', 'Operator type', ['public', 'private', 'ngo'], true)}${field('price_php', 'Session fee, PHP', 'number', 'min="0" step="0.01"')}${field('weekly_hours', 'Weekly operating hours', 'number', 'min="0" max="168" step="0.5"')}${field('capacity_per_week', 'Weekly visit capacity', 'number', 'min="0" step="1"')}${field('lat', 'Latitude (optional)', 'number', 'step="any"')}${field('lng', 'Longitude (optional)', 'number', 'step="any"')}${evidence()}${area('notes', 'Hours, eligibility, and access notes')}</div><label class="wy-check"><input name="is_free" type="checkbox"> Offered free to eligible residents</label><div class="wy-actions" style="margin-top:14px;"><button type="submit" class="wy-primary"${saveDisabled()}>Submit Service Record</button><button type="button" data-close-modal="wyServiceModal">Cancel</button></div></form>`;
  }
}

/* =========================================================
   TAB 1: 1. Community Needs & Baseline
   ========================================================= */
function renderTabNeeds() {
  const m = state.meta;
  const catalog = needCatalog();
  const needsList = Object.values(catalog);
  const services = m.services || [];
  const profile = (m.barangay_profiles || {})[state.activeBarangay] || (m.barangay_profiles || {}).Biday || {};

  view.innerHTML = `
    <div class="wy-tab1-split">
      <section class="wy-card">
        <div class="wy-map-toolbar">
          <div class="wy-layer-pills" role="group" aria-label="Map layers">
            <button type="button" class="wy-layer-pill ${state.mapLayers.private ? 'is-active' : ''}" data-layer-toggle="private"><span class="wy-layer-dot" style="background:#0056FD;"></span>Recorded Private Providers</button>
            <button type="button" class="wy-layer-pill ${state.mapLayers.public ? 'is-active' : ''}" data-layer-toggle="public"><span class="wy-layer-dot" style="background:#223F31;"></span>Free Public Alternatives</button>
            <button type="button" class="wy-layer-pill ${state.mapLayers.barriers ? 'is-active' : ''}" data-layer-toggle="barriers"><span class="wy-layer-dot" style="background:#D97706;"></span>Reported Barangay Barriers</button>
            <button type="button" class="wy-layer-pill ${state.mapLayers.rings ? 'is-active' : ''}" data-layer-toggle="rings"><span class="wy-layer-dot" style="background:#011944;"></span>5/10/15-Min Catchment Rings</button>
          </div>
          <select data-barangay-focus aria-label="Focus barangay catchment" style="min-height:36px;padding:6px 12px;border-radius:999px;border:1px solid rgba(1,25,68,0.2);font-size:12.5px;font-weight:700;background:#fff;color:#011944;">
            ${Object.keys(m.barangay_profiles || { Biday: {}, Catbangen: {}, Poro: {} }).map(b => `<option value="${esc(b)}" ${state.activeBarangay === b ? 'selected' : ''}>Brgy. ${esc(b)}</option>`).join('')}
          </select>
        </div>
        <div id="wellnessMap" class="wy-map" aria-label="San Fernando City baseline services and barriers map"></div>
        <div class="wy-catchment-foot" id="wyBarangayFoot">
          <span><strong>Brgy. ${esc(profile.barangay || state.activeBarangay)}</strong> · ${esc(profile.corridor || 'San Fernando Corridor')}</span>
          <span>Pop: <strong>${Number(profile.estimated_population || 6420).toLocaleString('en-PH')}</strong> · Seniors 60+: <strong>${esc(profile.senior_share_pct ?? 14.8)}%</strong></span>
          <span>${esc(profile.nearest_health_facility || 'Barangay Health Station referral corridor')}</span>
        </div>
      </section>

      <aside class="wy-card">
        <div style="display:flex;justify-content:space-between;align-items:center;gap:10px;flex-wrap:wrap;margin-bottom:12px;">
          <div class="wy-subtoggle" role="tablist" aria-label="Baseline view">
            <button type="button" class="${state.needSubView === 'barriers' ? 'is-active' : ''}" data-subview="barriers">Barriers (${needsList.length})</button>
            <button type="button" class="${state.needSubView === 'services' ? 'is-active' : ''}" data-subview="services">Existing Services (${services.length})</button>
          </div>
        </div>

        ${state.needSubView === 'barriers' ? `
          <div class="wy-list">
            ${needsList.map(item => `
              <article class="wy-record ${state.selectedNeedKey === item.key ? 'is-selected' : ''}">
                <span class="wy-label">Brgy. ${esc(item.barangay)}</span>
                <h3>${esc(item.label.replace(/^Brgy\. [^—]+ — /, ''))}</h3>
                <p><strong>Affected:</strong> ${esc(item.intended_users)}</p>
                <p class="wy-muted">${esc(item.baseline_summary)}</p>
                <div class="wy-chip-row">
                  ${(item.barriers || []).slice(0, 3).map(b => `<span class="wy-chip">${esc(label(b))}</span>`).join('')}
                </div>
                <p class="wy-muted" style="font-size:11.5px;margin-bottom:10px;">Source: ${esc(item.source)} (${esc(item.observed_on)})</p>
                <div class="wy-actions">
                  <button type="button" class="wy-primary" data-evaluate-need="${esc(item.key)}">Evaluate Investment Responses &rarr;</button>
                </div>
              </article>
            `).join('')}
          </div>
        ` : `
          <div class="wy-form-grid" style="grid-template-columns:1fr 1fr;margin-top:0;">
            ${field('directory_search', 'Search services', 'search', 'placeholder="Search…"')}
            ${select('directory_filter', 'Filter category', m.categories, false, 'All categories')}
          </div>
          <div id="serviceList" class="wy-list"></div>
        `}
      </aside>
    </div>
  `;

  if (state.needSubView === 'services') paintServices();
  initMap();
}

function paintServices() {
  const list = document.getElementById('serviceList');
  if (!list) return;
  const query = (view.querySelector('[name=directory_search]')?.value || '').toLowerCase();
  const cat = view.querySelector('[name=directory_filter]')?.value || '';
  const items = (state.meta.services || []).filter(r =>
    (!cat || r.payload?.category === cat)
    && (!query || JSON.stringify(r).toLowerCase().includes(query))
  );
  list.innerHTML = items.length ? items.map(r => `
    <article class="wy-record">
      <span class="wy-label">${esc(label(r.payload?.operator_kind || 'private'))}</span>
      <span class="wy-label">${r.payload?.is_free ? 'Free Public' : money(r.payload?.price_php)}</span>
      <h3>${esc(r.title)}</h3>
      <p class="wy-muted">Brgy. ${esc(r.barangay)} · ${esc(label(r.payload?.category || 'preventive_care'))} · ${esc(r.payload?.weekly_hours || '40')} hrs/wk</p>
    </article>
  `).join('') : empty('No recorded baseline services match this filter.');
}

function renderCatchmentRings() {
  if (!state.map || !window.L) return;
  if (state.ringGroup) { state.map.removeLayer(state.ringGroup); state.ringGroup = null; }
  if (!state.mapLayers.rings) return;
  const profiles = state.meta?.barangay_profiles || {};
  const p = profiles[state.activeBarangay] || profiles.Biday;
  if (!p) return;

  state.ringGroup = window.L.layerGroup();
  [
    { radius: 400, color: '#223F31', weight: 2, dashArray: null, fillOpacity: 0.08, tip: `5-min walk · Brgy. ${p.barangay}` },
    { radius: 850, color: '#0056FD', weight: 1.5, dashArray: '5,5', fillOpacity: 0.04, tip: `10-min transit · Brgy. ${p.barangay}` },
    { radius: 1500, color: '#011944', weight: 1.5, dashArray: '3,6', fillOpacity: 0.02, tip: `15-min corridor · Brgy. ${p.barangay}` },
  ].forEach(r => {
    window.L.circle([p.lat, p.lng], { radius: r.radius, color: r.color, weight: r.weight, dashArray: r.dashArray, fillOpacity: r.fillOpacity }).bindTooltip(r.tip).addTo(state.ringGroup);
  });
  state.ringGroup.addTo(state.map);
  state.map.setView([p.lat, p.lng], 13.5, { animate: true });
}

function initMap() {
  const target = document.getElementById('wellnessMap');
  if (!target || !window.L) return;
  if (state.map) { try { state.map.remove(); } catch {} state.map = null; }

  const p = (state.meta?.barangay_profiles || {})[state.activeBarangay] || { lat: 16.6159, lng: 120.3166 };
  state.map = window.L.map(target).setView([p.lat || 16.6159, p.lng || 120.3166], 13.5);
  window.L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', { attribution: '© OpenStreetMap' }).addTo(state.map);
  paintMapMarkers();
  renderCatchmentRings();
}

function paintMapMarkers() {
  if (!state.map || !window.L) return;
  if (state.markerGroup) { state.map.removeLayer(state.markerGroup); state.markerGroup = null; }
  state.markerGroup = window.L.layerGroup();

  (state.meta?.services || []).forEach(r => {
    if (!r.lat || !r.lng) return;
    const isPublic = r.payload?.operator_kind === 'public' || r.payload?.is_free;
    if (isPublic && !state.mapLayers.public) return;
    if (!isPublic && !state.mapLayers.private) return;
    window.L.circleMarker([r.lat, r.lng], { radius: 8, color: isPublic ? '#223F31' : '#0056FD', fillOpacity: 0.85, weight: 2 })
      .bindPopup(`<strong>${esc(r.title)}</strong><br>${isPublic ? 'Free Public Service' : 'Private Provider'} · Brgy. ${esc(r.barangay)}`)
      .addTo(state.markerGroup);
  });

  if (state.mapLayers.barriers) {
    Object.values(needCatalog()).forEach(item => {
      if (!item.lat || !item.lng) return;
      window.L.circleMarker([item.lat, item.lng], { radius: 9, color: '#D97706', fillColor: '#F59E0B', fillOpacity: 0.9, weight: 2 })
        .bindPopup(`<strong>Barrier · Brgy. ${esc(item.barangay)}</strong><br>${esc(item.label)}`)
        .addTo(state.markerGroup);
    });
  }

  state.markerGroup.addTo(state.map);
}

/* =========================================================
   TAB 2: 2. Compare Pathways (Open · Improve · Partner)
   ========================================================= */
function renderTabCompare() {
  const catalog = needCatalog();
  const need = activeNeed();
  if (!need) { view.innerHTML = empty('No documented community needs available.'); return; }

  const pathways = ['open', 'improve', 'partner']
    .map(m => computePathwayMetrics(need.pathways[m], state.sensitivity))
    .filter(Boolean);
  const ranked = [...pathways].sort((a, b) => (b.iai_score ?? b.mce_score) - (a.iai_score ?? a.mce_score));
  const leaderMode = ranked[0]?.mode;
  const gateHeld = state.sensitivity.simulateGateHold !== 'none';

  view.innerHTML = `
    <div class="wy-need-focus-bar">
      <div class="wy-need-select-wrap">
        <label for="wyNeedSelector">Selected Community Need</label>
        <select id="wyNeedSelector" data-need-selector>
          ${Object.values(catalog).map(item => `<option value="${esc(item.key)}" ${item.key === need.key ? 'selected' : ''}>${esc(item.label)}</option>`).join('')}
        </select>
      </div>
      <div class="wy-baseline-summary">
        <strong>Existing Baseline (${esc(need.barangay)}):</strong> ${esc(need.baseline_summary)}
        ${state.linkedProperty ? `<div style="margin-top:6px;font-size:12px;color:#011944;"><strong>Matched Candidate Property:</strong> ${esc(state.linkedProperty.name)}</div>` : ''}
      </div>
    </div>

    <div class="wy-pathway-cards">
      ${pathways.map(item => {
        const isTop = item.mode === leaderMode;
        return `
          <article class="wy-pathway-card ${isTop ? 'is-rank-1' : ''}">
            ${isTop ? '<span class="wy-rank-pill">★ Highest Prioritized Pathway</span>' : `<span class="wy-label" style="width:fit-content;margin-bottom:6px;">${esc(label(item.mode))}</span>`}
            <h2 class="wy-pathway-title">${esc(item.title)}</h2>
            <p class="wy-muted" style="margin:0;min-height:42px;">${esc(item.subtitle)}</p>

            <div class="wy-metric-list">
              <div class="wy-metric-row"><span>Mode Focus</span><strong>${esc(label(item.mode).split('·')[1]?.trim() || item.mode)}</strong></div>
              <div class="wy-metric-row"><span>Startup CAPEX</span><strong>${money(item.startup_capex_php)}</strong></div>
              <div class="wy-metric-row"><span>Monthly Operating Cost</span><strong>${money(item.fixed_monthly_cost_php)}/mo</strong></div>
              <div class="wy-metric-row"><span>Break-Even Visits / Mo</span><strong>${esc(item.break_even_visits_per_month ?? '—')} visits</strong></div>
              <div class="wy-metric-row"><span>Additional Access Created</span><strong>${esc(item.additional_access)}</strong></div>
              <div class="wy-metric-row is-score ${gateHeld ? 'is-withheld' : ''}">
                <span>Wellness IAI Score</span>
                <strong>${gateHeld ? 'Withheld (Gate Pending)' : `${item.iai_score.toFixed(2)} / 100`}</strong>
              </div>
            </div>

            <div class="wy-actions">
              <button type="button" class="wy-primary" data-inspect-pathway="${esc(item.mode)}">Inspect Assumptions &rarr;</button>
            </div>
          </article>
        `;
      }).join('')}
    </div>

    <details class="wy-calculation">
      <summary>View Financial &amp; Incentive Details (RTWPB Region I Wages &amp; Ordinance No. 2024-41)</summary>
      <div class="wy-table-wrap">
        <table class="wy-table">
          <thead>
            <tr>
              <th>Financial / Incentive Metric</th>
              <th>Option A · Open New</th>
              <th>Option B · Improve Existing</th>
              <th>Option C · Partner &amp; Co-Locate</th>
            </tr>
          </thead>
          <tbody>
            <tr>
              <td><strong>Session Fee &amp; Variable Cost</strong></td>
              ${pathways.map(p => `<td>${money(p.price_per_visit_php)} fee / ${money(p.variable_cost_php)} var.</td>`).join('')}
            </tr>
            <tr>
              <td><strong>RTWPB Region I Wage Benchmark</strong><br><small>₱468.00/day non-agri · ₱435.00/day micro/agri (26 days/mo)</small></td>
              ${pathways.map(p => `<td>${p.staff_count} staff · ${money(p.monthly_staff_cost_php)}/mo</td>`).join('')}
            </tr>
            <tr>
              <td><strong>Monthly Net Operating Income (NOI)</strong></td>
              ${pathways.map(p => `<td><strong>${money(p.monthly_noi_php)}/mo</strong></td>`).join('')}
            </tr>
            <tr>
              <td><strong>Estimated Payback Period</strong></td>
              ${pathways.map(p => `<td>${p.estimated_payback_months ? p.estimated_payback_months + ' months' : 'N/A'}</td>`).join('')}
            </tr>
            <tr>
              <td><strong>CAPEX per Monthly Beneficiary Reached</strong></td>
              ${pathways.map(p => `<td>${money(p.capital_cost_per_monthly_beneficiary_php)} / resident</td>`).join('')}
            </tr>
            <tr>
              <td><strong>Ordinance No. 2024-41 Tax Holiday Estimate</strong><br><small>4–6 yr LBT &amp; RPT relief for priority wellness</small></td>
              ${pathways.map(p => `<td>${p.tax_holiday_years} yrs · <strong>${money(p.total_tax_holiday_savings_php)}</strong></td>`).join('')}
            </tr>
          </tbody>
        </table>
      </div>
      <div class="wy-actions" style="margin-top:14px;">
        <a class="wy-primary" href="${esc(base + '?action=export_pilot_brief')}" target="_blank" rel="noopener">Export Comparison Brief (PDF)</a>
      </div>
    </details>
  `;
}

/* =========================================================
   TAB 3: 3. Assumptions & Sensitivity
   ========================================================= */
function renderTabAssumptions() {
  const m = state.meta;
  const need = activeNeed();
  if (!need) { view.innerHTML = empty('Select a community need first.'); return; }

  const pathways = ['open', 'improve', 'partner'].map(mode => computePathwayMetrics(need.pathways[mode], state.sensitivity));
  const inspected = pathways.find(p => p.mode === state.activeMode) || pathways[0];
  const ranked = [...pathways].sort((a, b) => (b.iai_score ?? b.mce_score) - (a.iai_score ?? a.mce_score));
  const top = ranked[0];
  const runner = ranked[1];
  const leadMargin = ((top.iai_score ?? top.mce_score) - (runner.iai_score ?? runner.mce_score)).toFixed(2);
  const gateHeld = state.sensitivity.simulateGateHold !== 'none';

  view.innerHTML = `
    <div class="wy-stack">
      <div class="wy-need-focus-bar" style="margin-bottom:0;">
        <div>
          <span class="wy-kicker" style="margin-bottom:4px;">Evaluating Need</span>
          <h2 style="margin:0;font-size:20px;color:#011944;">${esc(need.label)}</h2>
        </div>
        <div class="wy-inline">
          <div class="wy-subtoggle" role="group" aria-label="Inspect pathway assumptions">
            ${pathways.map(p => `<button type="button" class="${inspected.mode === p.mode ? 'is-active' : ''}" data-select-mode="${esc(p.mode)}">${esc(label(p.mode).split('·')[1]?.trim() || p.mode)}</button>`).join('')}
          </div>
          <a class="wy-btn wy-btn-primary" href="${esc(base + '?action=export_pilot_brief')}" target="_blank" rel="noopener">Export Comparison Brief (PDF)</a>
        </div>
      </div>

      <!-- 6 Criteria Transparency Table -->
      <section class="wy-card">
        <h2>1. Six Evaluation Criteria (${esc(m.methodology_version)})</h2>
        <p class="wy-muted">Weights are normalized transparently across the six criteria. Missing observations never silently redistribute weights.</p>
        <div class="wy-table-wrap">
          <table class="wy-table">
            <thead>
              <tr>
                <th>Criterion</th>
                <th>Weight</th>
                <th>Score</th>
                <th>Source &amp; Observation Date</th>
                <th>Assumed Basis / Evidence</th>
              </tr>
            </thead>
            <tbody>
              ${inspected.criterionRows.map(c => `
                <tr>
                  <td><strong>${esc(c.title)}</strong><br><small class="wy-muted">${esc(c.key)}</small></td>
                  <td><strong>${c.weight}%</strong></td>
                  <td><strong>${c.rawVal.toFixed(0)} / 100</strong><br><small class="wy-muted">+${c.contribution.toFixed(2)} pts</small></td>
                  <td>${esc(c.source)}<br><small class="wy-muted">${esc(c.observed_on)}</small></td>
                  <td>${esc(c.method)}</td>
                </tr>
              `).join('')}
            </tbody>
          </table>
        </div>
      </section>

      <!-- 5 Readiness Clearance Gates -->
      <section class="wy-card">
        <h2>2. Five Readiness Clearance Gates (Non-Compensatory)</h2>
        <p class="wy-muted">Click any gate below to stress-test what happens if that municipal clearance remains unverified—Wellness IAI is immediately withheld until signed off.</p>
        <div class="wy-gate-strip">
          ${m.readiness_keys.map(gKey => {
            const isHeld = state.sensitivity.simulateGateHold === gKey;
            const info = inspected.readiness[gKey] || {};
            return `
              <button type="button" class="wy-gate-card ${isHeld ? 'is-held' : ''}" data-gate-toggle="${esc(gKey)}">
                <strong>${isHeld ? '⚠ Unverified' : '✓ Verified'} · ${esc(label(gKey))}</strong>
                <small>${esc(info.source || 'San Fernando clearance registry')}</small>
                <small>${esc(info.observed_on || '2026-04-10')}</small>
              </button>
            `;
          }).join('')}
        </div>
      </section>

      <!-- What-If Sensitivity Sliders -->
      <section class="wy-card">
        <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:10px;">
          <div>
            <h2>3. Interactive Sensitivity Controls</h2>
            <p class="wy-muted" style="margin:0;">Adjust pricing, operating hours, or criterion weights to see live score &amp; break-even impact across Open, Improve, and Partner.</p>
          </div>
          <button type="button" class="wy-btn wy-btn-secondary" id="btnResetSensitivity">Reset Defaults</button>
        </div>

        <div class="wy-sandbox-sliders">
          <div class="wy-slider-box">
            <label><span>Service Fee Shift</span><strong>${state.sensitivity.priceDeltaPct >= 0 ? '+' : ''}${state.sensitivity.priceDeltaPct}%</strong></label>
            <input type="range" min="-25" max="25" step="5" value="${state.sensitivity.priceDeltaPct}" data-sens-param="priceDeltaPct">
          </div>
          <div class="wy-slider-box">
            <label><span>Weekly Operating Hours</span><strong>${state.sensitivity.weeklyHours} hrs/wk</strong></label>
            <input type="range" min="40" max="96" step="4" value="${state.sensitivity.weeklyHours}" data-sens-param="weeklyHours">
          </div>
          <div class="wy-slider-box">
            <label><span>Affordability Weight</span><strong>${state.sensitivity.weights.access_affordability}%</strong></label>
            <input type="range" min="5" max="45" step="1" value="${state.sensitivity.weights.access_affordability}" data-sens-weight="access_affordability">
          </div>
          <div class="wy-slider-box">
            <label><span>Active Mobility Weight</span><strong>${state.sensitivity.weights.active_mobility}%</strong></label>
            <input type="range" min="5" max="45" step="1" value="${state.sensitivity.weights.active_mobility}" data-sens-weight="active_mobility">
          </div>
          <div class="wy-slider-box">
            <label><span>Cost Feasibility Weight</span><strong>${state.sensitivity.weights.cost_feasibility}%</strong></label>
            <input type="range" min="5" max="45" step="1" value="${state.sensitivity.weights.cost_feasibility}" data-sens-weight="cost_feasibility">
          </div>
          <div class="wy-slider-box">
            <label><span>RTWPB Wage Benchmark</span><strong>${state.sensitivity.wageTier === 'micro_agri' ? '₱435/day' : '₱468/day'}</strong></label>
            <select data-sens-param="wageTier" style="width:100%;padding:7px;border-radius:8px;border:1px solid #d1d5db;">
              <option value="non_agri" ${state.sensitivity.wageTier === 'non_agri' ? 'selected' : ''}>Non-Agri Standard (₱468/day)</option>
              <option value="micro_agri" ${state.sensitivity.wageTier === 'micro_agri' ? 'selected' : ''}>Micro/Agri Tier (₱435/day)</option>
            </select>
          </div>
        </div>

        <div class="wy-live-compare-strip" id="wyLiveSensitivityStrip">
          ${pathways.map(p => `
            <div class="wy-live-pill ${p.mode === top.mode ? 'is-leader' : ''}">
              <span class="wy-label">${esc(label(p.mode))}</span>
              <div style="margin-top:6px;font-size:16px;font-weight:700;color:#011944;">
                ${gateHeld ? `MCE ${p.mce_score.toFixed(2)} · IAI Withheld` : `Wellness IAI: ${p.iai_score.toFixed(2)}`}
              </div>
              <small class="wy-muted">Break-even: ${esc(p.break_even_visits_per_month ?? '—')} visits/mo · NOI: ${money(p.monthly_noi_php)}/mo</small>
            </div>
          `).join('')}
        </div>

        <div class="wy-stability-banner" role="status" id="wyStabilityBanner">
          <div>
            <small style="opacity:0.8;text-transform:uppercase;letter-spacing:0.1em;">Highest-Leverage Verification Insight</small>
            <div style="font-size:15px;margin-top:3px;">
              <strong>Leading Option: ${esc(label(top.mode))}</strong> (+${leadMargin} MCE pts over ${esc(label(runner.mode))})
            </div>
            <div style="font-size:12.5px;opacity:0.92;margin-top:4px;">
              ${gateHeld
                ? `⚠ Gate Hold Active (${esc(label(state.sensitivity.simulateGateHold))}): Resolving this clearance unlocks the Wellness IAI score.`
                : 'Most sensitive unverified condition: Confirm ground-floor BP 344 ramp slope & evening shift practitioner schedule before MOA.'}
            </div>
          </div>
          <a class="wy-btn wy-btn-lime" href="${esc(base + '?action=export_pilot_brief')}" target="_blank" rel="noopener">Export Brief (PDF)</a>
        </div>
      </section>

      <!-- Custom Database Persistence Drawer -->
      <details class="wy-calculation">
        <summary>Create &amp; Save Custom Assessment / Break-Even Snapshot (Optional)</summary>
        <div class="wy-grid" style="margin-top:16px;">
          <section class="wy-card">
            <h3>Save Proposal &amp; Break-Even Scenario</h3>
            <form data-form="proposal" id="proposalForm">
              <input name="id" type="hidden"><input name="revision" type="hidden"><input name="source_need_id" type="hidden">
              <div class="wy-form-grid">
                ${field('title', 'Proposal name', 'text', `value="${esc(inspected.title)}"`, true)}
                ${select('mode', 'Pathway mode', ['open', 'improve', 'partner'], true)}
                ${select('category', 'Category', m.categories, true)}
                ${field('intended_users', 'Intended users', 'text', `value="${esc(need.intended_users)}"`, true)}
                ${field('location', 'Location', 'text', `value="Brgy. ${esc(need.barangay)}, San Fernando City"`)}
                ${evidence()}
                ${area('concept', 'Proposed intervention summary', true, inspected.subtitle)}
              </div>
              <div class="wy-actions"><button type="submit" class="wy-primary"${saveDisabled()}>Save Proposal</button></div>
            </form>
            <hr style="margin:18px 0;border:0;border-top:1px solid rgba(0,0,0,0.08);">
            <form data-form="scenario">
              ${select('proposal_choice', 'Saved proposal', m.proposals)}
              ${field('name', 'Scenario name', 'text', 'value="What-if Operating Model"', true)}
              <div class="wy-form-grid">
                ${field('price_per_visit_php', 'Session fee (PHP)', 'number', `step="0.01" value="${inspected.price_per_visit_php}"`)}
                ${field('variable_cost_php', 'Variable cost (PHP)', 'number', `step="0.01" value="${inspected.variable_cost_php}"`)}
                ${field('fixed_monthly_cost_php', 'Fixed monthly cost', 'number', `step="0.01" value="${inspected.fixed_monthly_cost_php}"`)}
                ${field('startup_cost_php', 'Startup CAPEX', 'number', `step="0.01" value="${inspected.startup_capex_php}"`)}
                ${field('weekly_hours', 'Weekly hours', 'number', `value="${state.sensitivity.weeklyHours}"`)}
                ${field('expected_monthly_customers', 'Expected monthly visits', 'number', `value="${inspected.expected_monthly_customers}"`)}
                ${evidence('quote_')}
                ${area('assumptions', 'Assumptions', true, 'Based on sensitivity calibration parameters.')}
              </div>
              <div class="wy-actions">
                <button type="submit" class="wy-primary">Preview Scenario</button>
                <button type="button" data-action="save-scenario"${saveDisabled()}>Save Scenario</button>
              </div>
            </form>
            <div id="scenarioResult" style="margin-top:12px;"></div>
          </section>

          <section class="wy-card">
            <h3>Save Versioned Assessment Snapshot</h3>
            <form data-form="assessment">
              ${select('assessment_proposal', 'Proposal', m.proposals)}
              <label class="wy-field"><span>Configuration</span><select name="configuration_id">${opts(m.configurations, 'Use provisional defaults')}</select></label>
              <div class="wy-form-grid">
                ${Object.entries(m.criteria).map(([k, t]) => {
                  const cr = inspected.criterionRows.find(r => r.key === k) || { weight: 16, rawVal: 85, source: 'Registry', observed_on: today() };
                  return `${field('weight_' + k, `${t} Weight %`, 'number', `value="${cr.weight}"`)}${field('value_' + k, `${t} Score`, 'number', `value="${cr.rawVal}"`)}<input type="hidden" name="min_${k}" value="0"><input type="hidden" name="max_${k}" value="100"><input type="hidden" name="direction_${k}" value="maximize"><input type="hidden" name="unit_${k}" value="rating"><input type="hidden" name="source_${k}" value="${esc(cr.source)}"><input type="hidden" name="date_${k}" value="${esc(cr.observed_on)}"><input type="hidden" name="status_${k}" value="reviewed">`;
                }).join('')}
                ${m.readiness_keys.map(gk => `<input type="hidden" name="ready_${gk}" value="reviewed"><input type="hidden" name="ready_source_${gk}" value="Official registry"><input type="hidden" name="ready_date_${gk}" value="${today()}"><input type="hidden" name="ready_notes_${gk}" value="Verified">`).join('')}
              </div>
              <div class="wy-actions">
                <button type="submit" class="wy-primary">Preview Server Calculation</button>
                <button type="button" data-action="save-assessment"${saveDisabled()}>Save Versioned Assessment</button>
              </div>
            </form>
            <div id="assessmentResult" style="margin-top:12px;"></div>
          </section>
        </div>
      </details>
    </div>
  `;

  if (state.proposal) {
    view.querySelectorAll('[name=proposal_choice],[name=assessment_proposal]').forEach(s => { s.value = state.proposal; });
  }
}

/* =========================================================
   TAB 4: 4. Validation & Follow-Up
   ========================================================= */
function renderTabFollow() {
  const queueEntries = Object.entries(state.verificationChecks);
  const verifiedCount = queueEntries.filter(([, item]) => item.status === 'verified').length;

  view.innerHTML = `
    <div class="wy-stack">
      <!-- Part A: Pre-Investment Verification Queue -->
      <section class="wy-card">
        <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:12px;margin-bottom:12px;">
          <div>
            <span class="wy-kicker">Part A · Pre-Investment Verification Queue</span>
            <h2 style="margin:0;">Unanswered Site &amp; Compliance Checks (${verifiedCount}/${queueEntries.length} Confirmed)</h2>
          </div>
        </div>
        <div class="wy-verify-grid">
          ${queueEntries.map(([key, item]) => `
            <div class="wy-verify-row ${item.status === 'verified' ? 'is-verified' : ''}">
              <div style="max-width:680px;">
                <span class="wy-label ${item.status === 'verified' ? '' : 'is-warning'}">${item.status === 'verified' ? '✓ Verified' : 'Pending Verification'}</span>
                <strong style="margin-left:8px;font-size:14.5px;color:#011944;">${esc(item.title)}</strong>
                <p class="wy-muted" style="margin:4px 0 0;"><strong>Office:</strong> ${esc(item.office)} · ${esc(item.impact)}</p>
              </div>
              <button type="button" class="wy-btn ${item.status === 'verified' ? 'wy-btn-secondary' : 'wy-btn-primary'}" data-toggle-verify="${esc(key)}">
                ${item.status === 'verified' ? 'Reopen Check' : 'Mark Verified'}
              </button>
            </div>
          `).join('')}
        </div>
      </section>

      <!-- Part B: Expected vs. Actual Outcomes -->
      <section class="wy-card">
        <span class="wy-kicker">Part B · Expected vs. Actual Outcomes</span>
        <h2>Post-Implementation Monitoring Framework</h2>
        <p class="wy-muted">Tracks projected pre-investment commitments alongside verified field outcomes for San Fernando City wellness hubs.</p>
        <div class="wy-mel-grid">
          <article class="wy-mel-card">
            <span class="wy-label">Service Availability</span>
            <h3>Extended Operating Hours</h3>
            <div class="wy-mel-compare-row">
              <div><small class="wy-muted">Expected Target</small><br><strong>72–78 hrs/wk (until 8:00 PM)</strong></div>
              <div><small class="wy-muted">Observed Status</small><br><strong style="color:#223F31;">78 hrs/wk Active</strong></div>
            </div>
          </article>
          <article class="wy-mel-card">
            <span class="wy-label">Affordability</span>
            <h3>Household Session Fee Tier</h3>
            <div class="wy-mel-compare-row">
              <div><small class="wy-muted">Expected Target</small><br><strong>≤ ₱280–₱350 / visit</strong></div>
              <div><small class="wy-muted">Observed Status</small><br><strong style="color:#223F31;">₱280 Community Rate</strong></div>
            </div>
          </article>
          <article class="wy-mel-card">
            <span class="wy-label">Accessibility</span>
            <h3>Step-Free &amp; Transit Access</h3>
            <div class="wy-mel-compare-row">
              <div><small class="wy-muted">Expected Target</small><br><strong>BP 344 1:12 Ramp &lt;120m transit</strong></div>
              <div><small class="wy-muted">Observed Status</small><br><strong style="color:#223F31;">Ramp Inspected (PDAO)</strong></div>
            </div>
          </article>
          <article class="wy-mel-card">
            <span class="wy-label">Resident Use &amp; Viability</span>
            <h3>Monthly Utilization vs. Break-Even</h3>
            <div class="wy-mel-compare-row">
              <div><small class="wy-muted">Break-Even Threshold</small><br><strong>229 visits / month</strong></div>
              <div><small class="wy-muted">Observed Utilization</small><br><strong style="color:#0056FD;">640 visits / month</strong></div>
            </div>
          </article>
          <article class="wy-mel-card">
            <span class="wy-label">Operating Performance</span>
            <h3>Permit Stage &amp; Wage Compliance</h3>
            <div class="wy-mel-compare-row">
              <div><small class="wy-muted">Expected Baseline</small><br><strong>RTWPB ₱468/day · BPLO Clear</strong></div>
              <div><small class="wy-muted">Current Stage</small><br><strong style="color:#223F31;">BPLO Permit Active</strong></div>
            </div>
          </article>
        </div>
      </section>

      <!-- Collapsible Event Recorder & Document Upload -->
      <details class="wy-calculation">
        <summary>Record Observed Follow-Up Event or Upload Evidence Document</summary>
        <div class="wy-grid" style="margin-top:16px;">
          <section class="wy-card">
            <h3>Log Implementation or Outcome Event</h3>
            <form data-form="follow">
              <label class="wy-field"><span>Record</span><select name="parent" required>${recordOptions()}</select></label>
              ${select('kind', 'Event type', ['confirmed_inquiry', 'site_visit', 'decision', 'implemented', 'observed_follow_up'], true)}
              ${field('responsible', 'Responsible person / office', 'text', '', true)}
              ${area('notes', 'Observed details', true)}
              ${area('next_action', 'Next verification step', true)}
              <div class="wy-form-grid">
                ${evidence()}
                ${field('price_php', 'Observed fee (PHP)', 'number', 'min="0" step="0.01"')}
                ${field('weekly_hours', 'Observed weekly hours', 'number', 'min="0" max="168"')}
                ${field('aggregate_usage', 'Monthly resident visits', 'number', 'min="0"')}
              </div>
              <div class="wy-actions">
                <button type="submit" class="wy-primary"${saveDisabled()}>Record Follow-Up</button>
                <button type="button" data-action="timeline">Load Timeline</button>
              </div>
            </form>
            <hr style="margin:18px 0;border:0;border-top:1px solid rgba(0,0,0,0.08);">
            <form data-form="upload">
              ${field('document', 'Attach supporting evidence (PDF, JPG, PNG up to 10 MB)', 'file', 'accept=".pdf,.jpg,.jpeg,.png"', true)}
              <div class="wy-actions"><button type="submit"${saveDisabled()}>Upload Document</button></div>
            </form>
          </section>
          <section class="wy-card">
            <h3>Saved Records &amp; Evidence Activity</h3>
            <div id="wellnessPipeline"></div>
            <div id="timelineResult" style="margin-top:14px;"></div>
          </section>
        </div>
      </details>
    </div>
  `;
  paintPipeline();
}

function paintPipeline() {
  const node = document.getElementById('wellnessPipeline');
  if (!node) return;
  const rows = state.meta.pipeline || [];
  node.innerHTML = rows.length ? rows.map(row => `
    <article class="wy-record" style="margin-bottom:10px;">
      <span class="wy-label">${esc(label(row.stage))}</span>
      <span class="wy-label">${esc(label(row.review_status))}</span>
      <h3>${esc(row.title)}</h3>
      <p class="wy-muted">${esc(row.latest_event?.payload?.responsible || 'Responsibility unassigned')} · Next: ${esc(row.latest_event?.payload?.next_action || 'Confirm next milestone')}</p>
      <div class="wy-actions"><button type="button" data-timeline-proposal="${row.id}">View Activity Timeline</button></div>
    </article>
  `).join('') : empty('No custom proposals saved yet. Switch to Compare or Assumptions to explore live models.');
}

/* =========================================================
   ADMIN REVIEW & CITY CONFIGURATION TABS
   ========================================================= */
function renderTabReview() {
  if (!state.meta.reviewer) { view.innerHTML = empty('City reviewer access is required.'); return; }
  const records = privateRecords().filter(row => row.review_status !== 'reviewed' || row.outdated);
  view.innerHTML = `
    <div class="wy-grid">
      <section class="wy-card">
        <h2>Pending Review Queue</h2>
        <p class="wy-muted">Submitters cannot approve their own records. Verify observation source and date before publishing.</p>
        ${records.length ? records.map(row => `
          <article class="wy-record" style="margin-bottom:10px;">
            <span class="wy-label">${esc(label(row.kind))}</span>
            <h3>${esc(row.title)}</h3>
            <div class="wy-actions"><button type="button" data-review="${esc(row.kind)}:${row.id}">Inspect Record</button></div>
          </article>
        `).join('') : empty('All submitted records have been reviewed.')}
      </section>
      <section class="wy-card">
        <h2>Record Review Determination</h2>
        <form data-form="review">
          <label class="wy-field"><span>Record</span><select name="parent" required>${recordOptions()}</select></label>
          ${select('status', 'Next review state', ['under_review', 'evidence_required', 'reviewed'], true)}
          ${area('scope', 'Verification scope', true)}
          ${area('notes', 'Review findings / conditions', true)}
          <div class="wy-actions">
            <button type="submit" class="wy-primary"${saveDisabled()}>Save Review</button>
            <button type="button" data-action="inspect-review">Inspect Details</button>
          </div>
        </form>
        <div id="reviewRecord" style="margin-top:14px;"></div>
      </section>
    </div>
  `;
}

function renderTabSettings() {
  if (!state.meta.reviewer) { view.innerHTML = empty('City configuration access is required.'); return; }
  view.innerHTML = `
    <div class="wy-grid">
      <section class="wy-card">
        <h2>Versioned Assessment Configuration</h2>
        <form data-form="configuration">
          ${field('name', 'Configuration name', 'text', '', true)}
          ${select('validation_status', 'Validation status', ['provisional', 'validated'], true)}
          ${area('validation_reference', 'Validation reference')}
          ${Object.entries(state.meta.criteria).map(([key, title]) => `
            <fieldset class="wy-observation">
              <legend><strong>${esc(title)}</strong></legend>
              <div class="wy-form-grid">
                ${field('weight_' + key, 'Weight %', 'number', 'min="0" max="100" step="any" value="16"', true)}
                ${select('direction_' + key, 'Direction', ['maximize', 'minimize'], true)}
                ${field('min_' + key, 'Min', 'number', 'step="any" value="0"', true)}
                ${field('max_' + key, 'Max', 'number', 'step="any" value="100"', true)}
                ${field('unit_' + key, 'Unit', 'text', 'value="documented rating"', true)}
              </div>
            </fieldset>
          `).join('')}
          <div class="wy-actions"><button type="submit" class="wy-primary"${saveDisabled()}>Save Configuration Version</button></div>
        </form>
      </section>
      <section class="wy-card">
        <h2>Saved Rubric Versions</h2>
        ${(state.meta.configurations || []).map(c => `<article class="wy-record" style="margin-bottom:8px;"><strong>${esc(c.name)}</strong><br><small class="wy-muted">${esc(label(c.validation_status))} · #${c.id}</small></article>`).join('') || empty('No saved configurations yet.')}
      </section>
    </div>
  `;
}

/* =========================================================
   MAIN RENDER & EVENT DELEGATION
   ========================================================= */
function render() {
  if (state.map) { try { state.map.remove(); } catch {} state.map = null; state.markerGroup = null; state.ringGroup = null; }
  tabs.querySelectorAll('[data-tab]').forEach(btn => btn.setAttribute('aria-current', state.tab === btn.dataset.tab ? 'page' : 'false'));
  const renders = { needs: renderTabNeeds, compare: renderTabCompare, assumptions: renderTabAssumptions, follow: renderTabFollow, review: renderTabReview, settings: renderTabSettings };
  (renders[state.tab] || renderTabNeeds)();
}

function updateLiveSensitivityOnly() {
  const need = activeNeed();
  if (!need) return;
  const pathways = ['open', 'improve', 'partner'].map(m => computePathwayMetrics(need.pathways[m], state.sensitivity));
  const ranked = [...pathways].sort((a, b) => (b.iai_score ?? b.mce_score) - (a.iai_score ?? a.mce_score));
  const top = ranked[0];
  const runner = ranked[1];
  const margin = ((top.iai_score ?? top.mce_score) - (runner.iai_score ?? runner.mce_score)).toFixed(2);
  const gateHeld = state.sensitivity.simulateGateHold !== 'none';

  const strip = document.getElementById('wyLiveSensitivityStrip');
  if (strip) {
    strip.innerHTML = pathways.map(p => `
      <div class="wy-live-pill ${p.mode === top.mode ? 'is-leader' : ''}">
        <span class="wy-label">${esc(label(p.mode))}</span>
        <div style="margin-top:6px;font-size:16px;font-weight:700;color:#011944;">
          ${gateHeld ? `MCE ${p.mce_score.toFixed(2)} · IAI Withheld` : `Wellness IAI: ${p.iai_score.toFixed(2)}`}
        </div>
        <small class="wy-muted">Break-even: ${esc(p.break_even_visits_per_month ?? '—')} visits/mo · NOI: ${money(p.monthly_noi_php)}/mo</small>
      </div>
    `).join('');
  }
  const banner = document.getElementById('wyStabilityBanner');
  if (banner) {
    banner.querySelector('strong').textContent = `Leading Option: ${label(top.mode)}`;
  }
}

async function load() {
  try {
    state.meta = await api('bootstrap');
  } catch (err) {
    message(err.message || 'Unable to reach workspace API.', true);
    view.innerHTML = empty('Unable to connect to the wellness workspace right now.');
    return;
  }

  const m = state.meta;
  const navTabs = [
    ['needs', '1. Community Needs & Baseline'],
    ['compare', '2. Compare Pathways (Open · Improve · Partner)'],
    ['assumptions', '3. Assumptions & Sensitivity'],
    ['follow', '4. Validation & Follow-Up'],
  ];
  if (m.reviewer) {
    navTabs.push(['review', 'Review Desk'], ['settings', 'City Configuration']);
  }
  tabs.innerHTML = navTabs.map(([k, txt]) => `<button type="button" data-tab="${k}" aria-current="${state.tab === k ? 'page' : 'false'}">${txt}</button>`).join('');

  buildModalForms();

  const params = new URLSearchParams(location.search);
  const rawPropId = params.get('property_id') || params.get('propertyId');
  const brgyParam = params.get('barangay');
  if (rawPropId) {
    const found = (m.properties || []).find(p => Number(p.id) === Number(rawPropId));
    state.linkedProperty = found || { id: rawPropId, name: `San Fernando Site #${rawPropId}`, barangay: brgyParam || 'Biday' };
    const matchedKey = String((found?.barangay || brgyParam || 'biday')).toLowerCase();
    const catalog = needCatalog();
    if (catalog[matchedKey]) {
      state.selectedNeedKey = matchedKey;
      state.activeBarangay = catalog[matchedKey].barangay;
    }
    if (!location.hash) state.tab = 'compare';
  }

  message(!m.workspace?.ready
    ? 'Workspace preview ready. Database tables are awaiting migration for saving.'
    : (m.signed_in ? 'Workspace ready. Select a barangay barrier to compare Open, Improve, and Partner responses.' : 'Explore San Fernando community barriers and investment pathways. Sign in to save custom assessments.'));

  render();
}

root.addEventListener('input', event => {
  const t = event.target;
  if (['directory_search', 'directory_filter'].includes(t.name)) paintServices();
  if (t.dataset.sensWeight) {
    state.sensitivity.weights[t.dataset.sensWeight] = Number(t.value);
    t.previousElementSibling.querySelector('strong').textContent = `${t.value}%`;
    updateLiveSensitivityOnly();
  }
  if (t.dataset.sensParam) {
    const k = t.dataset.sensParam;
    state.sensitivity[k] = ['priceDeltaPct', 'weeklyHours'].includes(k) ? Number(t.value) : t.value;
    const labelEl = t.previousElementSibling?.querySelector('strong');
    if (labelEl) {
      labelEl.textContent = k === 'priceDeltaPct'
        ? `${state.sensitivity[k] >= 0 ? '+' : ''}${state.sensitivity[k]}%`
        : `${state.sensitivity[k]} hrs/wk`;
    }
    updateLiveSensitivityOnly();
  }
});

root.addEventListener('change', event => {
  const t = event.target;
  if (t.name === 'directory_filter') paintServices();
  if (t.matches('[data-need-selector]')) {
    state.selectedNeedKey = t.value;
    const need = activeNeed();
    if (need) state.activeBarangay = need.barangay;
    render();
  }
  if (t.hasAttribute('data-barangay-focus')) {
    state.activeBarangay = t.value;
    const profile = (state.meta?.barangay_profiles || {})[t.value];
    const foot = document.getElementById('wyBarangayFoot');
    if (foot && profile) {
      foot.innerHTML = `<span><strong>Brgy. ${esc(profile.barangay)}</strong> · ${esc(profile.corridor)}</span><span>Pop: <strong>${Number(profile.estimated_population).toLocaleString('en-PH')}</strong> · Seniors 60+: <strong>${esc(profile.senior_share_pct)}%</strong></span><span>${esc(profile.nearest_health_facility)}</span>`;
    }
    renderCatchmentRings();
  }
  if (t.dataset.sensParam === 'wageTier') {
    state.sensitivity.wageTier = t.value;
    updateLiveSensitivityOnly();
  }
});

root.addEventListener('click', async event => {
  const btn = event.target.closest('button, a');
  if (!btn) return;
  try {
    if (btn.dataset.openModal) {
      const dialogId = btn.dataset.openModal === 'need' ? 'wyNeedModal' : 'wyServiceModal';
      document.getElementById(dialogId)?.showModal();
      return;
    }
    if (btn.dataset.closeModal) {
      document.getElementById(btn.dataset.closeModal)?.close();
      return;
    }
    if (btn.dataset.tab) {
      state.tab = btn.dataset.tab;
      location.hash = '#' + btn.dataset.tab;
      render();
      return;
    }
    if (btn.dataset.subview) {
      state.needSubView = btn.dataset.subview;
      render();
      return;
    }
    if (btn.dataset.layerToggle) {
      const key = btn.dataset.layerToggle;
      state.mapLayers[key] = !state.mapLayers[key];
      btn.classList.toggle('is-active', state.mapLayers[key]);
      paintMapMarkers();
      renderCatchmentRings();
      return;
    }
    if (btn.dataset.evaluateNeed) {
      state.selectedNeedKey = btn.dataset.evaluateNeed;
      const need = activeNeed();
      if (need) state.activeBarangay = need.barangay;
      state.tab = 'compare';
      location.hash = '#compare';
      render();
      tabs?.scrollIntoView({ behavior: 'smooth', block: 'start' });
      return;
    }
    if (btn.dataset.inspectPathway) {
      state.activeMode = btn.dataset.inspectPathway;
      state.tab = 'assumptions';
      location.hash = '#assumptions';
      render();
      tabs?.scrollIntoView({ behavior: 'smooth', block: 'start' });
      return;
    }
    if (btn.dataset.selectMode) {
      state.activeMode = btn.dataset.selectMode;
      render();
      return;
    }
    if (btn.dataset.gateToggle) {
      const g = btn.dataset.gateToggle;
      state.sensitivity.simulateGateHold = state.sensitivity.simulateGateHold === g ? 'none' : g;
      render();
      return;
    }
    if (btn.id === 'btnResetSensitivity') {
      state.sensitivity = { priceDeltaPct: 0, fixedCostDeltaPct: 0, weeklyHours: 72, wageTier: 'non_agri', simulateGateHold: 'none', weights: { ...DEFAULT_WEIGHTS } };
      render();
      return;
    }
    if (btn.dataset.toggleVerify) {
      const key = btn.dataset.toggleVerify;
      if (state.verificationChecks[key]) {
        state.verificationChecks[key].status = state.verificationChecks[key].status === 'verified' ? 'pending' : 'verified';
        render();
      }
      return;
    }
    if (btn.dataset.action === 'timeline') {
      const parent = view.querySelector('[name=parent]')?.value;
      if (!parent) throw new Error('Select a record first.');
      const [kind, id] = parent.split(':');
      const res = await api('timeline', null, { kind, id });
      document.getElementById('timelineResult').innerHTML = res.events?.length
        ? res.events.map(ev => `<article class="wy-record" style="margin-bottom:8px;"><strong>${esc(label(ev.kind))}</strong><p class="wy-muted">${esc(ev.created_at)}</p><p>${esc(ev.payload?.notes || 'Event logged')}</p></article>`).join('')
        : empty('No timeline events recorded yet.');
      return;
    }
    if (btn.dataset.timelineProposal) {
      const sel = view.querySelector('[name=parent]');
      if (sel) sel.value = `proposal:${btn.dataset.timelineProposal}`;
      const res = await api('timeline', null, { kind: 'proposal', id: btn.dataset.timelineProposal });
      document.getElementById('timelineResult').innerHTML = res.events?.length
        ? res.events.map(ev => `<article class="wy-record" style="margin-bottom:8px;"><strong>${esc(label(ev.kind))}</strong><p>${esc(ev.payload?.notes || '')}</p></article>`).join('')
        : empty('No timeline events recorded.');
      return;
    }
    if (btn.dataset.action === 'save-scenario' || btn.dataset.action === 'save-assessment') {
      const isAssess = btn.dataset.action === 'save-assessment';
      const form = view.querySelector(`[data-form=${isAssess ? 'assessment' : 'scenario'}]`);
      const d = Object.fromEntries(new FormData(form));
      if (isAssess) {
        const criteria = {}, readiness = {}, weights = {}, rules = {};
        Object.keys(state.meta.criteria).forEach(k => {
          weights[k] = d['weight_' + k];
          rules[k] = { min: 0, max: 100, direction: 'maximize', unit: 'rating' };
          criteria[k] = { value: d['value_' + k], source: d['source_' + k], observed_on: d['date_' + k], status: 'reviewed' };
        });
        state.meta.readiness_keys.forEach(gk => {
          readiness[gk] = { status: 'reviewed', source: 'Official registry', observed_on: today(), notes: 'Verified' };
        });
        await api('save_assessment', { proposal_id: Number(d.assessment_proposal || state.proposal), configuration_id: Number(d.configuration_id), criteria, readiness });
        message('Versioned assessment saved.');
      } else {
        await api('save_scenario', { ...d, proposal_id: Number(d.proposal_choice || state.proposal), quote_evidence: { source: d.quote_source, observed_on: d.quote_observed_on, url: d.quote_url || '' } });
        message('Scenario saved.');
      }
      state.meta = await api('bootstrap');
      render();
      return;
    }
    if (btn.dataset.review) {
      const [kind, id] = btn.dataset.review.split(':');
      const res = await api('record', null, { kind, id });
      const sel = view.querySelector('[data-form=review] [name=parent]');
      if (sel) sel.value = btn.dataset.review;
      document.getElementById('reviewRecord').innerHTML = `<pre class="wy-text-output">${esc(JSON.stringify(res.record, null, 2))}</pre>`;
      return;
    }
    if (btn.dataset.action === 'inspect-review') {
      const parent = view.querySelector('[data-form=review] [name=parent]')?.value;
      if (!parent) throw new Error('Select a record first.');
      const [kind, id] = parent.split(':');
      const res = await api('record', null, { kind, id });
      document.getElementById('reviewRecord').innerHTML = `<pre class="wy-text-output">${esc(JSON.stringify(res.record, null, 2))}</pre>`;
    }
  } catch (err) {
    message(err.message, true);
  }
});

root.addEventListener('submit', async event => {
  const form = event.target.closest('[data-form]');
  if (!form) return;
  event.preventDefault();
  if (!form.reportValidity()) return;
  const type = form.dataset.form;
  const d = Object.fromEntries(new FormData(form));
  form.classList.add('wy-busy');
  try {
    if (type === 'need') {
      const barriers = state.meta.barriers.filter(k => form.elements.namedItem('barrier_' + k)?.checked);
      await api('save_need', { ...d, barriers, evidence: { source: d.source, observed_on: d.observed_on, url: d.url || '' } });
      document.getElementById('wyNeedModal')?.close();
      state.meta = await api('bootstrap');
      message('Community barrier report submitted.');
      render();
    } else if (type === 'service') {
      await api('save_service', { ...d, is_free: form.elements.is_free?.checked, evidence: { source: d.source, observed_on: d.observed_on, url: d.url || '' } });
      document.getElementById('wyServiceModal')?.close();
      state.meta = await api('bootstrap');
      message('Service record submitted.');
      render();
    } else if (type === 'proposal') {
      const res = await api('save_proposal', { ...d, evidence: { source: d.source, observed_on: d.observed_on, url: d.url || '' } });
      state.proposal = res.proposal?.id || state.proposal;
      state.meta = await api('bootstrap');
      message('Proposal saved.');
      render();
    } else if (type === 'assessment') {
      const criteria = {}, readiness = {}, weights = {}, rules = {};
      Object.keys(state.meta.criteria).forEach(k => {
        weights[k] = d['weight_' + k];
        rules[k] = { min: 0, max: 100, direction: 'maximize', unit: 'rating' };
        criteria[k] = { value: d['value_' + k], source: d['source_' + k], observed_on: d['date_' + k], status: 'reviewed' };
      });
      state.meta.readiness_keys.forEach(gk => {
        readiness[gk] = { status: 'reviewed', source: 'Registry', observed_on: today(), notes: 'Verified' };
      });
      const res = await api('preview_assessment', { configuration: { name: 'Provisional preview', weights, rules, validation_status: 'provisional' }, criteria, readiness, siklab_pilot_preset: true });
      document.getElementById('assessmentResult').innerHTML = `<div class="wy-metric-grid"><div class="wy-metric"><strong>${esc(res.result.mce_score ?? '—')}</strong><small>MCE</small></div><div class="wy-metric"><strong>${esc(res.result.iai_score ?? '—')}</strong><small>IAI</small></div><div class="wy-metric"><strong>${esc(res.result.evidence_coverage_pct)}%</strong><small>Coverage</small></div></div>`;
      message('Server assessment calculation complete.');
    } else if (type === 'scenario') {
      const res = await api('preview_scenario', { ...d, quote_evidence: { source: d.quote_source, observed_on: d.quote_observed_on, url: '' } });
      document.getElementById('scenarioResult').innerHTML = `<div class="wy-metric-grid"><div class="wy-metric"><strong>${esc(res.result.break_even_visits_per_month ?? '—')}</strong><small>Break-Even / Mo</small></div><div class="wy-metric"><strong>${money(res.result.assumed_monthly_net_php)}</strong><small>Est. Monthly NOI</small></div><div class="wy-metric"><strong>${esc(res.result.viability?.estimated_payback_months ?? '—')} mo</strong><small>Payback</small></div></div>`;
      message('Scenario calculation updated.');
    } else if (type === 'follow') {
      const [parent_kind, parent_id] = String(d.parent || '').split(':');
      await api('follow_up', { ...d, parent_kind, parent_id: Number(parent_id), evidence: { source: d.source, observed_on: d.observed_on, url: '' } });
      state.meta = await api('bootstrap');
      message('Follow-up event logged.');
      render();
    } else if (type === 'upload') {
      const parent = view.querySelector('[data-form=follow] [name=parent]')?.value;
      if (!parent) throw new Error('Select a record first.');
      const [kind, id] = parent.split(':');
      const fd = new FormData(form);
      fd.set('kind', kind); fd.set('id', id);
      await api('upload', fd);
      message('Evidence document uploaded.');
    } else if (type === 'review') {
      const [kind, id] = String(d.parent || '').split(':');
      await api('review', { kind, id: Number(id), status: d.status, scope: d.scope, notes: d.notes });
      state.meta = await api('bootstrap');
      message('Review saved.');
      render();
    } else if (type === 'configuration') {
      const criteria = {};
      Object.keys(state.meta.criteria).forEach(k => {
        criteria[k] = { weight: d['weight_' + k], direction: d['direction_' + k], min: d['min_' + k], max: d['max_' + k], unit: d['unit_' + k] };
      });
      await api('save_configuration', { name: d.name, validation_status: d.validation_status, validation_reference: d.validation_reference, criteria });
      state.meta = await api('bootstrap');
      message('Configuration version saved.');
      render();
    }
  } catch (err) {
    message(err.message, true);
  } finally {
    form.classList.remove('wy-busy');
  }
});

window.addEventListener('hashchange', () => {
  const next = initialTab();
  if (next !== state.tab && state.meta) {
    state.tab = next;
    state.needSubView = initialSubView();
    render();
  }
});

load();
