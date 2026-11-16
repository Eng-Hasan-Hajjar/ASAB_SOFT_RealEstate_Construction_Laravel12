@props(['name'=>'building'])
<svg {{ $attributes->class('ops-icon') }} viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">
@switch($name)
@case('dashboard')<rect x="3" y="3" width="7" height="9" rx="1"/><rect x="14" y="3" width="7" height="5" rx="1"/><rect x="3" y="16" width="7" height="5" rx="1"/><rect x="14" y="12" width="7" height="9" rx="1"/>@break
@case('spark')<path d="M12 2l2.7 7.3L22 12l-7.3 2.7L12 22l-2.7-7.3L2 12l7.3-2.7zM19 2v4M17 4h4"/>@break
@case('layers')<path d="m12 3 9 5-9 5-9-5 9-5Zm-9 9 9 5 9-5M3 16l9 5 9-5"/>@break
@case('globe')<circle cx="12" cy="12" r="9"/><path d="M3 12h18M12 3a15 15 0 0 1 0 18 15 15 0 0 1 0-18Z"/>@break
@case('mail')<rect x="3" y="5" width="18" height="14" rx="2"/><path d="m3 7 9 7 9-7"/>@break
@case('door')<path d="M5 21V4a1 1 0 0 1 1-1h12a1 1 0 0 1 1 1v17M3 21h18M14 12h1"/>@break
@case('people')<circle cx="9" cy="7" r="3"/><path d="M2 20v-2a5 5 0 0 1 5-5h4a5 5 0 0 1 5 5v2M17 4a3 3 0 0 1 0 6m1 4a5 5 0 0 1 4 5v1"/>@break
@case('history')<path d="M3 12a9 9 0 1 1 2.5 6M3 7v5h5m4-5v5l3 2"/>@break
@case('wallet')<rect x="3" y="5" width="18" height="14" rx="2"/><path d="M21 10h-5a2 2 0 0 0 0 4h5m-4-2h.1"/>@break
@case('document')<path d="M6 3h8l4 4v14H6zM14 3v4h4M9 11h6m-6 4h6"/>@break
@case('calendar')<rect x="3" y="5" width="18" height="16" rx="2"/><path d="M7 3v4m10-4v4M3 10h18M8 15h2m4 0h2m-8 3h2"/>@break
@case('chart')<path d="M3 3v18h18M8 16v-5m5 5V6m5 10V9"/>@break
@case('upload')<path d="M4 16v4h16v-4M12 16V4m-5 5 5-5 5 5"/>@break
@case('tools')<path d="M14 6a5 5 0 0 0-6 6l-4 4a2.2 2.2 0 1 0 3 3l4-4a5 5 0 0 0 6-6l-3 3-3-3z"/>@break
@case('briefcase')<rect x="3" y="7" width="18" height="13" rx="2"/><path d="M8 7V4h8v3M3 12h18m-9 0v3"/>@break
@case('target')<circle cx="12" cy="12" r="9"/><circle cx="12" cy="12" r="5"/><circle cx="12" cy="12" r="1"/>@break
@case('book')<path d="M12 6a7 7 0 0 0-5-2H3v15h4a7 7 0 0 1 5 2 7 7 0 0 1 5-2h4V4h-4a7 7 0 0 0-5 2Zm0 0v15"/>@break
@case('settings')<circle cx="12" cy="12" r="3"/><path d="M9 3h6l1 3 3 1 2 4-2 2v3l-4 3-3-1-3 1-4-3v-3l-2-2 2-4 3-1z"/>@break
@case('menu')<rect x="3" y="4" width="18" height="16" rx="2"/><path d="M15 4v16M7 9h4m-4 4h4"/>@break
@case('sun')<circle cx="12" cy="12" r="4"/><path d="M12 2v2m0 16v2M2 12h2m16 0h2M5 5l1.5 1.5m11 11L19 19m0-14-1.5 1.5m-11 11L5 19"/>@break
@case('moon')<path d="M20.5 15.7A8.5 8.5 0 0 1 8.3 3.5a9 9 0 1 0 12.2 12.2Z"/>@break
@case('logout')<path d="M10 4H4v16h6m5-13 5 5-5 5m5-5H8"/>@break
@case('arrow')<path d="M19 12H5m6-6-6 6 6 6"/>@break
@case('check')<path d="m4 12 5 5L20 6"/>@break
@default<path d="M3 21h18M5 21V4a1 1 0 0 1 1-1h8a1 1 0 0 1 1 1v17m0-12h4v12M8 7h4m-4 4h4m-4 4h4"/>
@endswitch
</svg>
