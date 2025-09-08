// products.js

// handle clicks for qty buttons and tabs
document.addEventListener('click', (e) => {
  // qty
  if (e.target.matches('[data-plus], [data-minus]')) {
    const wrap = e.target.closest('.qty');
    const input = wrap.querySelector('input[type="number"]');
    const delta = e.target.hasAttribute('data-plus') ? 1 : -1;
    input.value = Math.max(1, (parseInt(input.value, 10) || 1) + delta);
  }

  // tabs
  if (e.target.matches('.tab')) {
    const tabs = e.target.closest('[data-tabs]');
    tabs.querySelectorAll('.tab').forEach(t => {
      t.classList.toggle('is-active', t === e.target);
      t.setAttribute('aria-selected', t === e.target ? 'true' : 'false');
    });
    tabs.querySelectorAll('.tabpanel').forEach(p => p.classList.remove('is-active'));
    const panelId = e.target.getAttribute('aria-controls');
    tabs.querySelector('#' + panelId).classList.add('is-active');
  }
});

