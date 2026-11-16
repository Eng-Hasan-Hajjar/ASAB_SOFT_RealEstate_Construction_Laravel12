'use strict';
(() => {
  const editor = document.querySelector('[data-gallery-editor]');
  if (!editor) return;
  const list = editor.querySelector('[data-spaces-list]');
  const template = editor.querySelector('[data-space-template]');
  let serial = Math.max(-1, ...[...list.querySelectorAll('select')].map(el => Number(el.name.match(/\[(\d+)\]/)?.[1] ?? -1))) + 1;
  const urls = new Map();
  function release(input) {
    (urls.get(input) ?? []).forEach(URL.revokeObjectURL);
    urls.delete(input);
  }
  editor.querySelector('[data-add-space]').addEventListener('click', () => {
    if (list.children.length >= 30) { window.alert('الحد الأقصى 30 مساحة يُمكن إضافتها.'); return; }
    list.insertAdjacentHTML('beforeend', template.innerHTML.replaceAll('__IDX__', String(serial++)));
    list.lastElementChild.querySelector('input:not([type=hidden])')?.focus();
  });
  editor.addEventListener('click', event => {
    const button = event.target.closest('[data-remove-space]');
    if (!button) return;
    const row = button.closest('[data-space-row]');
    if (row.querySelector('input[type=hidden]') && !window.confirm('ستُحذف المساحة وصورها عند حفظ النموذج. هل تريد المتابعة؟')) return;
    row.querySelectorAll('[data-photo-input]').forEach(release);
    row.remove();
  });
  editor.addEventListener('change', event => {
    const input = event.target;
    if (!input.matches('[data-photo-input]')) return;
    release(input);
    const preview = input.parentElement.querySelector('[data-photo-previews]');
    preview.replaceChildren();
    const current = [];
    for (const file of Array.from(input.files).slice(0, 12)) {
      if (!['image/jpeg','image/png','image/webp'].includes(file.type)) continue;
      const url = URL.createObjectURL(file);
      current.push(url);
      const img = document.createElement('img');
      img.src = url; img.alt = file.name; preview.append(img);
    }
    urls.set(input, current);
  });
})();
