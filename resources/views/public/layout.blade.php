<!DOCTYPE html>
<html lang="ar" dir="rtl"><head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="theme-color" content="#081c30"><meta name="description" content="عصب سوفت — بوابة العقارات والإيجارات والمقاولات في السعودية والبحرين. استكشف العقارات والوحدات المنشورة وتواصل مع الفريق.">
<title>@yield('title','السوق العقاري') | عصب سوفت ASAB SOFT</title>
<link rel="icon" type="image/svg+xml" href="{{ asset('assets/images/brand.svg') }}">
<link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=IBM+Plex+Sans+Arabic:wght@400;500;600;700&family=Alexandria:wght@500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="{{ asset('assets/css/marketplace.css') }}?v=3">
</head><body>
<a class="skip" href="#main">الانتقال إلى المحتوى</a>
<div class="topline"><div class="pub-container topline-inner"><span>عصب سوفت | حلول عقارية ومقاولات متكاملة</span><span>السعودية 🇸🇦 · البحرين 🇧🇭</span></div></div>
<header class="pub-header"><div class="pub-container pub-nav">
<a class="pub-brand" href="{{ route('public.home') }}"><span class="brand-square"><img src="{{ asset('assets/images/brand.svg') }}" alt=""></span><span><strong>عصب سوفت</strong><small>ASAB SOFT REAL ESTATE</small></span></a>
<button class="nav-toggle" id="nav-toggle" aria-controls="pub-links" aria-expanded="false" aria-label="فتح قائمة الموقع">☰</button>
<nav id="pub-links" class="pub-links" aria-label="التنقل الرئيسي"><a href="{{ route('public.home') }}" class="{{ request()->routeIs('public.home')?'current':'' }}">الرئيسية</a><a href="{{ route('public.catalogue') }}" class="{{ request()->routeIs('public.catalogue','public.property')?'current':'' }}">تصفح العقارات</a><a href="{{ route('public.home') }}#solutions">حلولنا</a><a href="{{ route('public.home') }}#about">عن المنصة</a><a class="pub-login" href="{{ auth()->check()?(\App\Support\Workspace::ownerHome()?route('platform.index'):route('dashboard')):route('login') }}">{{ auth()->check()?'لوحة التحكم':'دخول الشركات' }} ↗</a></nav>
</div></header>
@if(session('success'))<div class="pub-container pub-alert" role="status">✓ {{ session('success') }}</div>@endif
@if($errors->any())<div class="pub-container pub-alert err" role="alert"><strong>يرجى تصحيح التالي:</strong><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
<main id="main">@yield('content')</main>
<footer class="pub-footer"><div class="pub-container footer-grid"><div><a href="{{ route('public.home') }}" class="footer-logo">عصب سوفت <span>ASAB SOFT</span></a><p>واجهة تسويقية لعقارات الشركات المسجلة. بيانات التوافر والأسعار تخضع لتأكيد الجهة العارضة.</p></div><div><strong>استكشف</strong><a href="{{ route('public.catalogue') }}">العقارات المعروضة</a><a href="{{ route('public.home') }}#solutions">إدارة الأملاك والمقاولات</a><a href="{{ route('login') }}">دخول الإدارة</a></div><div><strong>الأسواق</strong><span>المملكة العربية السعودية</span><span>مملكة البحرين</span><small>هذه نسخة برمجية تأسيسية وليست خدمة تعاقدية رسمية.</small></div></div><div class="pub-container footer-bottom">© {{ now()->year }} عصب سوفت · جميع الحقوق محفوظة. <span>Laravel 12 · Built for growth</span></div></footer>
<script src="{{ asset('assets/js/marketplace.js') }}?v=3" defer></script>
</body></html>