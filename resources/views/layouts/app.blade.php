<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
 <meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
 <meta name="csrf-token" content="{{ csrf_token() }}">
 <meta name="theme-color" content="#08192d">
 <title>@yield('title','لوحة العمليات') | عصب سوفت ASAB SOFT</title>
 <link rel="icon" href="{{ asset('assets/images/brand.svg') }}" type="image/svg+xml">
 <link rel="stylesheet" href="{{ asset('assets/css/app.css') }}?v=3.0">
</head>
<body>
 <div class="shell" id="appShell">
  <div class="backdrop" id="navBackdrop"></div>
  <aside class="sidebar" id="sidebar">
   <a href="{{ \App\Support\Workspace::ownerHome()?route('platform.index'):route('dashboard') }}" class="brand">
    <span class="brand-mark"><img src="{{ asset('assets/images/brand.svg') }}" alt=""></span>
    <span><strong>عصب سوفت</strong><small>ASAB SOFT · OPERATIONS</small></span>
   </a>
   <div class="side-workspace">
    <div class="workspace-avatar">{{ \App\Support\Workspace::ownerHome()?'A':mb_substr(\App\Support\Workspace::company()?->name??'ع',0,1) }}</div>
    <span><small>مساحة العمل</small><b>{{ \App\Support\Workspace::ownerHome()?'مالك المنصة':\App\Support\Workspace::company()?->name }}</b></span>
   </div>
   <nav class="side-nav" aria-label="القائمة الرئيسية">
   @if(\App\Support\Workspace::ownerHome())
    <span class="nav-heading">إدارة المنصة</span>
    <a class="nav-link {{ request()->routeIs('platform.*')?'active':'' }}" href="{{ route('platform.index') }}"><span class="nav-emoji">◈</span> الشركات والعملاء</a>
   @else
    <span class="nav-heading">نظرة عامة</span>
    <a class="nav-link {{ request()->routeIs('dashboard')?'active':'' }}" href="{{ route('dashboard') }}"><span class="nav-emoji">▦</span> لوحة المؤشرات</a>
    <a class="nav-link {{ request()->routeIs('assistant.*')?'active':'' }}" href="{{ route('assistant.index') }}"><span class="nav-emoji">✧</span> المساعد المحلي <span class="nav-tag">LOCAL</span></a>
    <span class="nav-heading">العقارات والتأجير</span>
    <a class="nav-link {{ request()->routeIs('listings.*')?'active':'' }}" href="{{ route('listings.index') }}"><span class="nav-emoji">◈</span> إعلانات العقارات والموقع <span class="nav-tag">WEB</span></a>
    <a class="nav-link {{ request()->routeIs('inquiries.*')?'active':'' }}" href="{{ route('inquiries.index') }}"><span class="nav-emoji">✉</span> استفسارات الزوار</a>
    @foreach(['properties','units','contacts'] as $mod)
      @if(array_key_exists($mod,\App\Support\ModuleRegistry::modulesForRole(\App\Support\Workspace::role())))
      <a class="nav-link {{ request()->is('manage/'.$mod.'*')?'active':'' }}" href="{{ route('modules.index',$mod) }}"><span class="nav-emoji">{{ ['properties'=>'⌂','units'=>'▤','contacts'=>'♙'][$mod] }}</span>{{ config('modules.'.$mod.'.title') }}</a>
      @endif
    @endforeach
    <a class="nav-link {{ request()->routeIs('asab.history.rentals')?'active':'' }}" href="{{ route('asab.history.rentals') }}"><span class="nav-emoji">▤</span> السجل التاريخي للوحدات <span class="nav-tag">Excel</span></a>
    @if(in_array(\App\Support\Workspace::role(),['company_admin','manager','accountant'],true))
    <a class="nav-link {{ request()->routeIs('asab.history.finance')?'active':'' }}" href="{{ route('asab.history.finance') }}"><span class="nav-emoji">◉</span> دفتر التحصيل المستورد <span class="nav-tag">Excel</span></a>
    @endif
    <a class="nav-link {{ request()->routeIs('leases.*')?'active':'' }}" href="{{ route('leases.index') }}"><span class="nav-emoji">▣</span> عقود الإيجار</a>
    <a class="nav-link {{ request()->routeIs('invoices.*')?'active':'' }}" href="{{ route('invoices.index') }}"><span class="nav-emoji">＄</span> الفواتير والتحصيل</a>
    <a class="nav-link {{ request()->routeIs('reservations.*')?'active':'' }}" href="{{ route('reservations.index') }}"><span class="nav-emoji">◷</span> الحجوزات والإشغال</a>
    <span class="nav-heading">المالية والتقارير</span>
    <a class="nav-link {{ request()->routeIs('billing.*')?'active':'' }}" href="{{ route('billing.index') }}"><span class="nav-emoji">▣</span> استحقاقات الإيجار</a>
    <a class="nav-link {{ request()->routeIs('finance.*')?'active':'' }}" href="{{ route('finance.index') }}"><span class="nav-emoji">◉</span> الصندوق والبنوك والمصروفات</a>
    <a class="nav-link {{ request()->routeIs('reports.*')?'active':'' }}" href="{{ route('reports.index') }}"><span class="nav-emoji">▥</span> التقارير المالية والتشغيلية</a>
    @if(in_array(\App\Support\Workspace::role(),['company_admin','manager','accountant'],true))
    <a class="nav-link {{ request()->routeIs('imports.*')?'active':'' }}" href="{{ route('imports.index') }}"><span class="nav-emoji">⇧</span> مراجعة ملفات Excel</a>
    @endif
    <span class="nav-heading">المقاولات والتشغيل</span>
    @foreach(['projects','tasks','expenses','contractors','tickets'] as $mod)
     @if(array_key_exists($mod,\App\Support\ModuleRegistry::modulesForRole(\App\Support\Workspace::role())))
     <a class="nav-link {{ request()->is('manage/'.$mod.'*')?'active':'' }}" href="{{ route('modules.index',$mod) }}"><span class="nav-emoji">{{ ['projects'=>'▱','tasks'=>'☷','expenses'=>'◉','contractors'=>'♜','tickets'=>'⚒'][$mod] }}</span>{{ config('modules.'.$mod.'.title') }}</a>
     @endif
    @endforeach
    <span class="nav-heading">النمو والمعرفة</span>
    @foreach(['leads','knowledge'] as $mod)
     @if(array_key_exists($mod,\App\Support\ModuleRegistry::modulesForRole(\App\Support\Workspace::role())))
     <a class="nav-link {{ request()->is('manage/'.$mod.'*')?'active':'' }}" href="{{ route('modules.index',$mod) }}"><span class="nav-emoji">{{ $mod==='leads'?'◎':'◇' }}</span>{{ config('modules.'.$mod.'.title') }}</a>
     @endif
    @endforeach
    @if(in_array(\App\Support\Workspace::role(),['company_admin','manager'],true))
    <span class="nav-heading">إعدادات الإدارة</span>
    <a class="nav-link {{ request()->routeIs('staff.*')?'active':'' }}" href="{{ route('staff.index') }}"><span class="nav-emoji">♧</span> الموظفون والصلاحيات</a>
    @endif
   @endif
    <a class="nav-link" href="{{ route('public.home') }}" target="_blank"><span class="nav-emoji">↗</span> عرض الموقع العام</a>
    @if(\App\Support\Workspace::acting())
    <form method="POST" action="{{ route('platform.leave') }}" class="workspace-return">@csrf<button type="submit">↩ العودة إلى لوحة مالك المنصة</button></form>
    @endif
    <a class="nav-link {{ request()->routeIs('profile.*')?'active':'' }}" href="{{ route('profile.edit') }}"><span class="nav-emoji">⚙</span> ملفي وأمان الحساب</a>
   </nav>
   <div class="sidebar-footer"><span class="live-dot"></span> منصة داخلية قابلة للتوسّع <small>v3.0 · Laravel 12</small></div>
  </aside>
  <div class="main-column">
   <header class="topbar">
    <div class="topbar-title"><button class="menu-btn" id="menuBtn" aria-label="فتح القائمة" aria-controls="sidebar" aria-expanded="false">☰</button>
    <span class="topbar-breadcrumb">عصب سوفت <em>/</em> @yield('title','لوحة العمليات')</span></div>
    <div class="topbar-actions">
     @if(\App\Support\Workspace::acting())<span class="owner-acting-badge">عرض وإدارة باسم مالك المنصة</span>@endif
     @if(!\App\Support\Workspace::ownerHome())<span class="country-pill">{{ \App\Support\Workspace::company()?->country_code==='BH'?'🇧🇭 البحرين':'🇸🇦 السعودية' }} <b>{{ \App\Support\Workspace::company()?->currency }}</b></span>@endif
     <span class="profile-chip"><span>{{ mb_substr(auth()->user()->name,0,1) }}</span>{{ auth()->user()->name }}</span>
     <form action="{{ route('logout') }}" method="POST">@csrf<button class="logout-btn" aria-label="تسجيل الخروج" title="تسجيل الخروج">↗</button></form>
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
<script src="{{ asset('assets/js/app.js') }}?v=2.0" defer></script>
 @stack('scripts')
</body>
</html>
