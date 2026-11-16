<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
 <meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
 <meta name="csrf-token" content="{{ csrf_token() }}">
 <meta name="theme-color" content="#f5f7fb">
 <script>
 try {
   const choice=localStorage.getItem('asab-ui-theme');
   document.documentElement.dataset.theme=choice==='dark'||choice==='light'?choice:(matchMedia('(prefers-color-scheme: dark)').matches?'dark':'light');
   if(localStorage.getItem('asab-ui-rail')==='1')document.documentElement.dataset.rail='compact';
 } catch (_) {}
 </script>
 <title>@yield('title','لوحة العمليات') | عصب سوفت ASAB SOFT</title>
 <link rel="icon" href="{{ asset('assets/images/brand.svg') }}" type="image/svg+xml">
 <link rel="stylesheet" href="{{ asset('assets/css/app.css') }}?v=6">
 <link rel="stylesheet" href="{{ asset('assets/css/asab-v6.css') }}?v=6">
 <link rel="stylesheet" href="{{ asset('assets/css/asab-v7.css') }}?v=7.1">
</head>
<body>
 <div class="shell" id="appShell">
  <div class="backdrop" id="navBackdrop"></div>
  <aside class="sidebar" id="sidebar">
   <a href="{{ \App\Support\Workspace::ownerHome()?route('platform.index'):route('dashboard') }}" class="brand" aria-label="عصب سوفت · الصفحة الرئيسية">
    <span class="brand-mark"><img src="{{ asset('assets/images/brand.svg') }}" alt=""></span>
    <span><strong>عصب سوفت</strong><small>ASAB SOFT · OPERATIONS</small></span>
   </a>
   <div class="side-workspace">
    <div class="workspace-avatar">{{ \App\Support\Workspace::ownerHome()?'A':mb_substr(\App\Support\Workspace::company()?->name??'ع',0,1) }}</div>
    <span><small>مساحة العمل</small><b>{{ \App\Support\Workspace::ownerHome()?'مالك المنصة':\App\Support\Workspace::company()?->name }}</b></span>
   </div>
   @php
    $workspaceRole=\App\Support\Workspace::role();
    $ownerHome=\App\Support\Workspace::ownerHome();
    $modules=\App\Support\ModuleRegistry::modulesForRole($workspaceRole);
    $financeVisible=in_array($workspaceRole,['company_admin','manager','accountant'],true);
    $sections=$ownerHome ? ['إدارة المنصة'=>[
        ['platform.index','الشركات والعملاء','people','platform.*'],
    ]] : [
        'نظرة عامة'=>[['dashboard','لوحة المؤشرات','dashboard'],['assistant.index','المساعد المحلي','spark','assistant.*']],
        'العقارات والتأجير'=>[
            ['explorer.index','الواجهة التفاعلية','layers','explorer.*'],
            ['listings.index','إعلانات العقارات والموقع','globe','listings.*'],
            ['inquiries.index','استفسارات الزوار','mail','inquiries.*'],
            ['module:properties',null,'building'],['module:units',null,'door'],['module:contacts',null,'people'],
            ['leases.index','عقود الإيجار','document','leases.*'],
            ['reservations.index','الحجوزات والإشغال','calendar','reservations.*'],
        ],
        'المالية والتقارير'=>[
            ['invoices.index','الفواتير والتحصيل','document','invoices.*'],
            ['billing.index','استحقاقات الإيجار','calendar','billing.*'],
            ['finance.index','الصندوق والبنوك والمصروفات','wallet','finance.*'],
            ['reports.index','التقارير المالية والتشغيلية','chart','reports.*'],
        ],
        'المقاولات والتشغيل'=>[
            ['module:projects',null,'briefcase'],['module:tasks',null,'check'],['module:expenses',null,'wallet'],
            ['module:contractors',null,'people'],['module:tickets',null,'tools'],
        ],
        'النمو والمعرفة'=>[['module:leads',null,'target'],['module:knowledge',null,'book']],
        'السجلات والمراجعة'=>[
            ['asab.history.rentals','سجل الإيجارات التاريخي','history'],
            ...($financeVisible?[
                ['asab.history.finance','مراجعة التحصيلات السابقة','wallet','asab.history.finance|asab.receipts.*'],
                ['imports.index','استيراد البيانات','upload','imports.*'],
            ]:[]),
        ],
        ...((in_array($workspaceRole,['company_admin','manager'],true))?['إعدادات الإدارة'=>[['staff.index','الموظفون والصلاحيات','people','staff.*']]]:[]),
    ];
   @endphp
   <nav class="side-nav" aria-label="القائمة الرئيسية">
    @foreach($sections as $heading=>$links)
     <div class="ops-nav-group"><span class="nav-heading">{{ $heading }}</span>
       @foreach($links as $link)
        @php([$name,$label,$icon,$active]=$link+[3=>null])
        @if(str_starts_with($name,'module:'))
         @php($key=substr($name,7))
         @if(isset($modules[$key]))
          <x-ops-nav :url="route('modules.index',$key)" :label="$modules[$key]['title']" :icon="$icon" :active="request()->is('manage/'.$key.'*')" />
         @endif
        @else
         <x-ops-nav :url="route($name)" :label="$label" :icon="$icon" :active="request()->routeIs(...explode('|',$active??$name))" />
        @endif
       @endforeach
     </div>
    @endforeach
    <div class="ops-nav-group">
     <x-ops-nav :url="route('public.home')" label="عرض الموقع العام" icon="globe" :external="true" />
     @if(\App\Support\Workspace::acting())
      <form method="POST" action="{{ route('platform.leave') }}" class="workspace-return">@csrf
       <button type="submit" aria-label="العودة إلى لوحة مالك المنصة" title="العودة إلى لوحة مالك المنصة"><x-ops-icon name="arrow" /> <span class="ops-nav-text">العودة إلى لوحة مالك المنصة</span></button>
      </form>
     @endif
     <x-ops-nav :url="route('profile.edit')" label="ملفي وأمان الحساب" icon="settings" :active="request()->routeIs('profile.*')" />
    </div>
   </nav>
   <div class="sidebar-footer"><span class="live-dot"></span> منصة داخلية قابلة للتوسّع <small>v7.0 · Laravel 12</small></div>
  </aside>
  <div class="main-column">
   <header class="topbar">
    <div class="topbar-title"><button class="menu-btn" id="menuBtn" type="button" aria-label="فتح القائمة" aria-controls="sidebar" aria-expanded="true" title="طي أو فتح القائمة"><x-ops-icon name="menu" /></button>
    <span class="topbar-breadcrumb">عصب سوفت <em>/</em> @yield('title','لوحة العمليات')</span></div>
    <div class="topbar-actions">
     <button class="ops-tool theme-toggle" id="themeToggle" type="button" aria-label="تبديل المظهر" aria-pressed="false" title="تبديل المظهر">
      <x-ops-icon name="moon" class="ops-icon-dark" /><x-ops-icon name="sun" class="ops-icon-light" />
     </button>
     @if(\App\Support\Workspace::acting())<span class="owner-acting-badge">عرض وإدارة باسم مالك المنصة</span>@endif
     @if(!\App\Support\Workspace::ownerHome())<span class="country-pill">{{ \App\Support\Workspace::company()?->country_code==='BH'?'🇧🇭 البحرين':'🇸🇦 السعودية' }} <b>{{ \App\Support\Workspace::company()?->currency }}</b></span>@endif
     <span class="profile-chip"><span>{{ mb_substr(auth()->user()->name,0,1) }}</span>{{ auth()->user()->name }}</span>
     <form action="{{ route('logout') }}" method="POST">@csrf<button class="logout-btn" aria-label="تسجيل الخروج" title="تسجيل الخروج"><x-ops-icon name="logout" /></button></form>
    </div>
   </header>
   <main class="content" id="content">
    @if(session('success'))<div class="flash success" role="status">✓ {{ session('success') }}</div>@endif
    @if($errors->any())<div class="flash danger" role="alert"><b>يرجى مراجعة المدخلات:</b><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
    @yield('content')
    <footer class="page-footer"><span>© {{ now()->year }} عصب سوفت | ASAB SOFT</span><span>حلول لإدارة العقارات والمقاولات · السعودية والبحرين</span></footer>
   </main>
  </div>
 </div>
<script src="{{ asset('assets/js/app.js') }}?v=7" defer></script>
 <script src="{{ asset('assets/js/asab-v6.js') }}?v=6" defer></script>
 @stack('scripts')
</body>
</html>
