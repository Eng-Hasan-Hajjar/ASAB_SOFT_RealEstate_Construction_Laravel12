'use strict';
(() => {
  const root=document.documentElement;
  const shell=document.getElementById('appShell');
  const sidebar=document.getElementById('sidebar');
  const menu=document.getElementById('menuBtn');
  const backdrop=document.getElementById('navBackdrop');
  const theme=document.getElementById('themeToggle');
  const compact=window.matchMedia('(max-width: 900px)');
  const preference=window.matchMedia('(prefers-color-scheme: dark)');
  const save=(key,value)=>{try{localStorage.setItem(key,value);}catch(_){}};
  const read=(key)=>{try{return localStorage.getItem(key);}catch(_){return null;}};
  function updateMenu(){
    if(!shell||!sidebar||!menu)return;
    const open=compact.matches?shell.classList.contains('nav-open'):root.dataset.rail!=='compact';
    if(compact.matches&&!open&&sidebar.contains(document.activeElement))menu.focus();
    sidebar.inert=compact.matches&&!open;
    menu.setAttribute('aria-expanded',String(open));
    const label=open?'طي القائمة':'فتح القائمة';
    menu.setAttribute('aria-label',label);menu.title=label;
  }
  function closeDrawer(restoreFocus=false){
    if(!shell?.classList.contains('nav-open'))return;
    shell.classList.remove('nav-open');
    if(restoreFocus)menu?.focus();
    updateMenu();
  }
  menu?.addEventListener('click',()=>{
    if(compact.matches){
      const shown=shell.classList.toggle('nav-open');
      updateMenu();
      if(shown)sidebar.querySelector('.nav-link.active,.nav-link')?.focus();
    }else{
      root.dataset.rail=root.dataset.rail==='compact'?'expanded':'compact';
      save('asab-ui-rail',root.dataset.rail==='compact'?'1':'0');
      updateMenu();
    }
  });
  backdrop?.addEventListener('click',()=>closeDrawer(true));
  compact.addEventListener('change',()=>{closeDrawer();updateMenu();});
  document.addEventListener('keydown',e=>{if(e.key==='Escape'&&!document.querySelector('dialog[open]'))closeDrawer(true);});
  document.querySelectorAll('.side-nav a').forEach(a=>a.addEventListener('click',()=>closeDrawer()));
  document.querySelectorAll('.js-confirm').forEach(form=>form.addEventListener('submit',e=>{if(!window.confirm(form.dataset.confirm||'هل أنت متأكد؟'))e.preventDefault();}));
  function setTheme(name){
    const dark=name==='dark';root.dataset.theme=dark?'dark':'light';
    const label=dark?'تفعيل المظهر الفاتح':'تفعيل المظهر الداكن';
    theme?.setAttribute('aria-label',label);theme?.setAttribute('aria-pressed',String(dark));
    if(theme)theme.title=label;
    document.querySelector('meta[name="theme-color"]')?.setAttribute('content',dark?'#111927':'#f4f7fb');
  }
  setTheme(root.dataset.theme??(preference.matches?'dark':'light'));
  theme?.addEventListener('click',()=>{
    setTheme(root.dataset.theme==='dark'?'light':'dark');save('asab-ui-theme',root.dataset.theme);
  });
  preference.addEventListener('change',()=>{if(!read('asab-ui-theme'))setTheme(preference.matches?'dark':'light');});
  updateMenu();
})();

// ASAB V5: accessible modal detail loading, independently scoped and read-only.
(()=>{
  const dialog=document.getElementById('asabDetailsDialog');
  if(!dialog || typeof dialog.showModal !== 'function')return;
  const content=document.getElementById('asabDetailsContent');
  const heading=document.getElementById('asabDetailsHeading');
  let requestController=null;
  document.querySelectorAll('.view-detail-btn').forEach(link=>link.addEventListener('click',async event=>{
    if(event.button!==0 || event.ctrlKey || event.metaKey || event.shiftKey || event.altKey)return;
    event.preventDefault();
    if(requestController)requestController.abort();
    requestController=new AbortController();
    heading.textContent=link.dataset.modalTitle || 'تفاصيل السجل';
    content.textContent='جارٍ تحميل التفاصيل...';
    if(!dialog.open)dialog.showModal();
    try{
      const response=await fetch(link.dataset.modalUrl,{credentials:'same-origin',headers:{'Accept':'text/html','X-Requested-With':'XMLHttpRequest'},signal:requestController.signal});
      if(!response.ok || response.redirected)throw new Error('HTTP '+response.status);
      // HTML is emitted by the authenticated Blade preview and Blade escapes all user values.
      content.innerHTML=await response.text();
    }catch(error){if(error.name!=='AbortError')content.textContent='تعذر عرض التفاصيل. حدّث الصفحة وحاول مجدداً.';}
  }));
  document.querySelectorAll('[data-close-details]').forEach(button=>button.addEventListener('click',()=>dialog.close()));
  dialog.addEventListener('click',event=>{if(event.target===dialog)dialog.close();});
  dialog.addEventListener('close',()=>{if(requestController)requestController.abort(); content.textContent='';});
})();
