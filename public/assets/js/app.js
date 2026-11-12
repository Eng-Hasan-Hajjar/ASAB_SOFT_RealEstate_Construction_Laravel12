'use strict';
(()=>{
  const btn=document.getElementById('menuBtn');
  const shell=document.getElementById('appShell');
  const backdrop=document.getElementById('navBackdrop');
  function toggle(open){if(!shell)return;shell.classList.toggle('nav-open',open);btn?.setAttribute('aria-expanded',String(open));}
  btn?.addEventListener('click',()=>toggle(!shell.classList.contains('nav-open')));
  backdrop?.addEventListener('click',()=>toggle(false));
  document.addEventListener('keydown',e=>{if(e.key==='Escape')toggle(false)});
  document.querySelectorAll('.js-confirm').forEach(form=>form.addEventListener('submit',e=>{if(!window.confirm(form.dataset.confirm||'هل أنت متأكد؟'))e.preventDefault();}));
  document.querySelectorAll('.side-nav a').forEach(a=>a.addEventListener('click',()=>toggle(false)));
})();
