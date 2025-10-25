
document.addEventListener('DOMContentLoaded', () => {
  // ---------- ELEMENTS ----------
  const els = {
    input:   document.getElementById('productSearch'),
    results: document.getElementById('psResults'),
    btn:     document.querySelector('.product-search .sbtn'),
    form:    document.querySelector('.product-search')
  };

  if (!els.input || !els.results) {
    console.warn('[search] Missing #productSearch or #psResults');
    return;
  }

  // Progressive enhancement: make sure ARIA roles/attrs are set
  if (!els.results.getAttribute('role')) els.results.setAttribute('role','listbox');
  els.input.setAttribute('aria-autocomplete','list');
  els.input.setAttribute('aria-haspopup','listbox');
  els.input.setAttribute('aria-controls','psResults');
  els.input.setAttribute('aria-expanded','false');

  // Bring dropdown above everything just in case
  els.results.style.zIndex = String(Math.max(9999, parseInt(getComputedStyle(els.results).zIndex || '0',10)));

  // ---------- DATA LOADING ----------
  // Try multiple paths; support array or {products:[...]}.
  const CANDIDATE_URLS = [
    '/products.json',
    '/assets/products.json',
    '/data/products.json'
  ];

  let catalog = [];
  let index = []; // [{p, haystack, name, category}]
  let activeIndex = -1;

  const esc    = s => String(s ?? '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
  const norm   = s => String(s || '').toLowerCase();
  const tokens = q => norm(q).split(/\s+/).filter(Boolean);
  const debounce = (fn, ms=150) => { let t; return (...a)=>{ clearTimeout(t); t=setTimeout(()=>fn(...a), ms); }; };

  function normalizeProducts(json) {
    if (Array.isArray(json)) return json;
    if (json && Array.isArray(json.products)) return json.products;
    return [];
  }

  async function tryFetch(url) {
    try {
      const res = await fetch(url + '?cb=' + Date.now(), { cache: 'no-store' });
      if (!res.ok) throw new Error('HTTP ' + res.status);
      const json = await res.json();
      const list = normalizeProducts(json);
      console.log('[search] loaded', url, 'count:', list.length);
      return list;
    } catch (e) {
      console.warn('[search] fetch failed', url, e);
      return null;
    }
  }

  async function loadCatalog() {
    if (catalog.length) return catalog;

    // Optional: immediate fallback from a global
    if (Array.isArray(window.PRODUCTS)) {
      catalog = window.PRODUCTS;
      console.log('[search] using window.PRODUCTS:', catalog.length);
    }

    if (!catalog.length) {
      for (const url of CANDIDATE_URLS) {
        const list = await tryFetch(url);
        if (list && list.length) { catalog = list; break; }
      }
    }

    if (!catalog.length) {
      console.warn('[search] No products loaded. Verify products.json path/shape.');
    }

    index = catalog.map(p => ({
      p,
      haystack: norm([
        p.name, p.category, p.slug, p.id,
        ...(Array.isArray(p.forms)   ? p.forms   : []),
        ...(Array.isArray(p.bullets) ? p.bullets : []),
        ...((Array.isArray(p.chemInfo) ? p.chemInfo : []).map(ci => `${ci.label} ${ci.value}`))
      ].filter(Boolean).join(' ')),
      name: norm(p.name || ''),
      category: norm(p.category || '')
    }));

    return catalog;
  }

  // ---------- SCORING & SEARCH ----------
  function scoreEntry(entry, qTokens) {
    const { name, category, haystack } = entry;
    let score = 0;
    for (const t of qTokens) {
      if (name.startsWith(t))      score += 8;
      else if (name.includes(t))   score += 6;
      if (category.startsWith(t))  score += 4;
      else if (category.includes(t)) score += 2;
      if (haystack.includes(t))    score += 1;
    }
    return score;
  }

  function doSearch(q) {
    const qTokens = tokens(q);
    if (!qTokens.length) return [];
    return index
      .map(e => ({ e, s: scoreEntry(e, qTokens) }))
      .filter(x => x.s > 0)
      .sort((a,b) => b.s - a.s)
      .slice(0, 20)
      .map(x => x.e.p);
  }

  // ---------- RENDER ----------
  const labelCategory = c =>
    c === 'peptides' ? 'Peptides' :
    c === 'sarms-performance' ? 'SARMs & Performance' :
    c === 'supplies' ? 'Supplies' : (c || '');

  const minMaxFromRanges = (ranges) => {
    if (!Array.isArray(ranges) || !ranges.length) return null;
    const nums = ranges.map(r => (r && r.price != null) ? Number(r.price) : NaN).filter(Number.isFinite);
    if (!nums.length) return null;
    return { min: Math.min(...nums), max: Math.max(...nums) };
  };
  const priceText = (p) => {
    if (typeof p.price === 'number' && !Number.isNaN(p.price)) return `$${p.price.toFixed(2)}`;
    const mm = minMaxFromRanges(p.ranges);
    if (!mm) return '';
    return (mm.min === mm.max) ? `$${mm.min.toFixed(2)}` : `From $${mm.min.toFixed(2)}`;
  };

  function showResults() {
    els.results.hidden = false;
    els.input.setAttribute('aria-expanded','true');
  }
  function hideResults() {
    els.results.hidden = true;
    els.input.setAttribute('aria-expanded','false');
    activeIndex = -1;
  }

  function renderResults(list) {
    activeIndex = -1;
    if (!list.length) {
      els.results.innerHTML = `<div class="ps-empty">No matches.</div>`;
      showResults();
      return;
    }
    els.results.innerHTML = list.map((p,i) => `
      <a class="ps-item" role="option" aria-selected="${i===activeIndex}" href="/product/${encodeURIComponent(p.slug)}/">
        <div class="ps-thumb"><img src="${esc(p.image || '/images/placeholder.png')}" alt=""></div>
        <div>
          <div class="ps-name">${esc(p.name)}</div>
          <div class="ps-meta">${esc(labelCategory(p.category))}${Array.isArray(p.forms)&&p.forms.length? ' · ' + esc(p.forms.join(', ')) : ''}</div>
        </div>
        <div class="ps-price">${esc(priceText(p))}</div>
      </a>
    `).join('');
    showResults();
  }

  // ---------- HANDLERS ----------
  const runSearch = async () => {
    const q = els.input.value;
    if (!q.trim()) { hideResults(); return; }
    await loadCatalog();
    if (!catalog.length) return; // nothing to search
    renderResults(doSearch(q));
  };

  const onInput = debounce(runSearch, 120);

  function onKeyDown(e) {
    const items = Array.from(els.results.querySelectorAll('.ps-item'));
    if (els.results.hidden || !items.length) return;

    if (e.key === 'ArrowDown' || e.key === 'ArrowUp') {
      e.preventDefault();
      activeIndex = (e.key === 'ArrowDown')
        ? Math.min(items.length - 1, activeIndex + 1)
        : Math.max(0, activeIndex - 1);
      items.forEach((el, i) => el.setAttribute('aria-selected', i === activeIndex));
      items[activeIndex]?.scrollIntoView({ block: 'nearest' });
    } else if (e.key === 'Enter') {
      if (activeIndex >= 0 && items[activeIndex]) items[activeIndex].click();
      else runSearch();
    } else if (e.key === 'Escape') {
      hideResults();
      els.input.blur();
    }
  }

  // Close on outside click
  document.addEventListener('click', (e) => {
    if (!els.results.contains(e.target) && e.target !== els.input) hideResults();
  });

  // Show results if focus with existing value
  els.input.addEventListener('focus', () => { if (els.input.value.trim()) runSearch(); });

  // Wire up
  els.input.addEventListener('input', onInput);
  els.input.addEventListener('keydown', onKeyDown);
  els.btn && els.btn.addEventListener('click', runSearch);

  // Optional: initial preload & smoke test
  loadCatalog().then(() => {
    if (!catalog.length) console.warn('[search] No products found on load.');
  });
});
