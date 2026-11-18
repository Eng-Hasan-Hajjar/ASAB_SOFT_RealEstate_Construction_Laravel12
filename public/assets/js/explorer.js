'use strict';
// ASAB Property Explorer: lightweight quick-preview popover for a unit card.
// No network request — the card already renders everything the preview shows (code, floor,
// type, status); this just surfaces it closer to the tap/click without opening the full,
// server-rendered details modal (still available via the "عرض التفاصيل الكاملة" link below,
// and via the card's own main link, both of which reuse the existing .view-detail-btn modal).
(() => {
  const popover = document.getElementById('v6QuickPreview');
  if (!popover) return;
  const codeEl = popover.querySelector('[data-peek-code]');
  const statusEl = popover.querySelector('[data-peek-status]');
  const floorEl = popover.querySelector('[data-peek-floor]');
  const typeEl = popover.querySelector('[data-peek-type]');
  const link = popover.querySelector('[data-peek-link]');
  let current = null;

  function close() {
    if (popover.hidden) return;
    popover.hidden = true;
    current?.setAttribute('aria-expanded', 'false');
    current = null;
  }

  function openFor(trigger) {
    if (current === trigger) { close(); return; }
    let data = {};
    try { data = JSON.parse(trigger.dataset.peek || '{}'); } catch (_) { data = {}; }
    current?.setAttribute('aria-expanded', 'false');
    current = trigger;
    codeEl.textContent = data.code || '';
    statusEl.textContent = data.status || '';
    floorEl.textContent = data.floor || '';
    typeEl.textContent = data.type || '';
    popover.className = 'v6-quickpreview v6-state-' + (data.state || '');
    link.href = data.detailUrl || '#';
    link.dataset.modalUrl = data.detailUrl || '';
    link.dataset.modalTitle = data.detailTitle || '';
    trigger.setAttribute('aria-expanded', 'true');
    popover.hidden = false;
    const rect = trigger.getBoundingClientRect();
    // getBoundingClientRect() always returns physical (left-based) viewport coordinates,
    // whatever the page's writing direction — so the popover must be placed with the
    // physical `left` property, never the logical `inset-inline-start` (which resolves to
    // `right` in this RTL page and would place it off-screen on the wrong side).
    popover.style.top = (rect.bottom + window.scrollY + 6) + 'px';
    popover.style.left = (rect.left + window.scrollX) + 'px';
    requestAnimationFrame(() => {
      const pRect = popover.getBoundingClientRect();
      if (pRect.right > window.innerWidth - 8) {
        popover.style.left = Math.max(8, window.innerWidth - pRect.width - 8) + 'px';
      }
      if (pRect.left < 8) popover.style.left = '8px';
    });
  }

  document.querySelectorAll('.v6-peek').forEach(btn => {
    btn.addEventListener('click', event => {
      event.preventDefault();
      event.stopPropagation();
      openFor(btn);
    });
  });
  link?.addEventListener('click', () => close());
  document.addEventListener('click', event => {
    if (popover.hidden) return;
    if (popover.contains(event.target) || event.target.closest('.v6-peek')) return;
    close();
  });
  document.addEventListener('keydown', event => { if (event.key === 'Escape') close(); });
  window.addEventListener('resize', close);
  window.addEventListener('scroll', close, true);
})();
