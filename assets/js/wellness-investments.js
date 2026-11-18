(() => {
  'use strict';
  const root = document.getElementById('wellnessInvestmentHub');
  if (!root) return;
  let data;
  try { data = JSON.parse(document.getElementById('wellnessInvestmentData').textContent); }
  catch { return; } // Server-rendered content and links remain usable.
  const search = root.querySelector('[data-hub-search]');
  const category = root.querySelector('[data-hub-category]');
  const featured = [...root.querySelectorAll('[data-hub-featured]')];
  const inventory = [...root.querySelectorAll('[data-hub-inventory]')];
  const directory = document.getElementById('wiFullDirectory');
  function filter() {
    const query = search.value.trim().toLocaleLowerCase();
    const selected = category.value;
    let featuredCount = 0, inventoryCount = 0;
    for (const records of [featured, inventory]) {
      records.forEach(card => {
        const match = (!selected || card.dataset.category === selected) && card.dataset.search.toLocaleLowerCase().includes(query);
        card.hidden = !match;
        if (match) records === featured ? featuredCount++ : inventoryCount++;
      });
    }
    root.querySelector('[data-hub-filter-count]').textContent = `${featuredCount} featured ${featuredCount === 1 ? 'facility' : 'facilities'} · ${inventoryCount} inventory ${inventoryCount === 1 ? 'record' : 'records'}${query || selected ? ' match your filters' : ''}`;
    root.querySelector('[data-no-featured]').hidden = featuredCount > 0;
    root.querySelector('[data-no-inventory]').hidden = inventoryCount > 0;
    if ((query || selected) && !featuredCount && inventoryCount) directory.open = true;
  }
  search.addEventListener('input', filter);
  category.addEventListener('change', filter);
  root.querySelector('[data-hub-clear]').addEventListener('click', () => { search.value = ''; category.value = ''; filter(); search.focus(); });
  const ideaDialog = document.getElementById('wiIdeaDialog');
  const facilityDialog = document.getElementById('wiFacilityDialog');
  const safeSource = value => {
    try { const url = new URL(value); return url.protocol === 'https:' && !url.username && !url.password ? url.href : ''; } catch { return ''; }
  };
  root.addEventListener('click', event => {
    const ideaButton = event.target.closest('[data-hub-idea]');
    const idea = ideaButton && data.ideas.find(item => item.id === ideaButton.dataset.hubIdea);
    if (idea) {
      ideaDialog.querySelector('#wiIdeaTitle').textContent = idea.title;
      ideaDialog.querySelector('[data-idea-description]').textContent = idea.description;
      ideaDialog.querySelector('[data-idea-fit]').textContent = idea.fit;
      ideaDialog.querySelector('[data-idea-evidence]').textContent = `${idea.evidence_basis} ${idea.source_summary || ''}`;
      const steps = ideaDialog.querySelector('[data-idea-steps]');
      steps.replaceChildren(...(idea.next_steps || [idea.validation]).map(text => { const li = document.createElement('li'); li.textContent = text; return li; }));
      const source = ideaDialog.querySelector('[data-idea-source]');
      const url = safeSource(idea.source_url);
      source.hidden = !url;
      if (url) { source.href = url; source.textContent = `${idea.source_title} ↗`; } else source.removeAttribute('href');
      ideaDialog.showModal();
    }
    const facilityButton = event.target.closest('[data-invest-facility]');
    const facility = facilityButton && data.featured_facilities.find(item => item.id === facilityButton.dataset.investFacility);
    if (facility) {
      facilityDialog.querySelector('#wiFacilityTitle').textContent = facility.name;
      facilityDialog.querySelector('[data-facility-address]').textContent = facility.address;
      facilityDialog.querySelector('[data-facility-investment]').textContent = facility.investment_note;
      const provider = facilityDialog.querySelector('[data-provider-link]');
      const url = safeSource(facility.contact_url || facility.source_url);
      provider.hidden = !url;
      if (url) provider.href = url; else provider.removeAttribute('href');
      facilityDialog.showModal();
    }
  });
  [ideaDialog, facilityDialog].forEach(dialog => {
    dialog.addEventListener('click', event => {
      if (event.target.closest('[data-hub-close]')) dialog.close();
      if (event.target === dialog) {
        const bounds = dialog.getBoundingClientRect();
        if (event.clientX < bounds.left || event.clientX > bounds.right || event.clientY < bounds.top || event.clientY > bounds.bottom) dialog.close();
      }
    });
  });
  const sectionLinks = [...root.querySelectorAll('[data-hub-section]')];
  function markSection(id) {
    sectionLinks.forEach(link => { if (link.dataset.hubSection === id) link.setAttribute('aria-current','location'); else link.removeAttribute('aria-current'); });
  }
  const syncHash = () => {
    const id = location.hash.slice(1);
    markSection(['facilities','invest','build'].includes(id) ? id : id === 'wiSites' ? 'invest' : 'facilities');
  };
  window.addEventListener('hashchange', syncHash);
  syncHash();
  if ('IntersectionObserver' in window) {
    const observer = new IntersectionObserver(entries => {
      const entry = entries.filter(item => item.isIntersecting).sort((a,b) => a.boundingClientRect.top - b.boundingClientRect.top)[0];
      if (entry) markSection(entry.target.id);
    }, {rootMargin:'-20% 0px -55% 0px'});
    ['facilities','invest','build'].forEach(id => observer.observe(document.getElementById(id)));
  }
  root.querySelectorAll('.wi-site-image img').forEach(img => img.addEventListener('error', () => { img.src = `${window.SFC_APP_CONFIG?.assetBase || 'assets'}/images/sfcView.png`; }, {once:true}));
})();
