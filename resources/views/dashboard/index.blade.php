@extends('layouts.app')
@section('title','لوحة المؤشرات')
@section('content')
<div class="dashboard-quick-links"><a class="quick-feature" href="{{ route('modules.create','properties') }}">⌂ <span><b>إضافة عقار</b><small>إنشاء سجل عقاري جديد</small></span> ←</a><a class="quick-feature" href="{{ route('modules.create','units') }}">▤ <span><b>إضافة وحدة</b><small>تحديد تفاصيل وسعر الوحدة</small></span> ←</a><a class="quick-feature" href="{{ route('listings.index') }}">◈ <span><b>إعلانات الموقع</b><small>نشر العقارات للزوار</small></span> ←</a><a class="quick-feature" href="{{ route('public.home') }}" target="_blank">↗ <span><b>الموقع العام</b><small>المعاينة المباشرة للزوار</small></span></a></div>
<div class="dashboard-quick-links v3-quick-links"><a class="quick-feature" href="{{ route('finance.index') }}">◉ <span><b>الصندوق والبنوك</b><small>تحصيل ومصاريف وتحويلات</small></span> ←</a><a class="quick-feature" href="{{ route('reports.index') }}">▥ <span><b>التقارير الشهرية</b><small>حسب العقار والتاريخ</small></span> ←</a><a class="quick-feature" href="{{ route('reservations.index') }}">◷ <span><b>الحجوزات</b><small>متابعة إشغال الوحدات</small></span> ←</a><a class="quick-feature" href="{{ route('imports.index') }}">⇧ <span><b>استيراد Excel</b><small>منطقة مراجعة آمنة</small></span> ←</a></div>
<div class="page-heading dashboard-heading"><div><span class="eyebrow">LIVE BUSINESS OVERVIEW</span><h1>صباح الإنجاز، {{ explode(' ',auth()->user()->name)[0] }} <span class="heading-star">✦</span></h1><p>هذه نظرة موحدة على أعمال {{ \App\Support\Workspace::company()->name }} حتى {{ now()->format('Y/m/d') }}.</p></div><a class="btn btn-dark" href="{{ route('assistant.index') }}">✧ اسأل المساعد المحلي</a></div>
<div class="banner"><div class="banner-copy"><span class="badge-light">ASAB INTELLIGENCE</span><h2>وضوح أكثر. قرارات أسرع.<br><span>منصة واحدة لكل أعمالك.</span></h2><p>تابع وحداتك وشؤون مستأجريك ومشاريع المقاولات من لوحة تحكم مترابطة.</p><div class="banner-actions"><a href="{{ route('modules.index','properties') }}" class="btn btn-mint">استعراض العقارات ←</a><a href="{{ route('modules.index','projects') }}" class="btn btn-glass">مشاريع المقاولات</a></div></div><div class="banner-graphic" aria-hidden="true"><div class="graphic-circle g1"></div><div class="graphic-circle g2"></div><div class="graphic-circle g3"></div><div class="graphic-center">✧</div><span class="graphic-node gn1">▦</span><span class="graphic-node gn2">◈</span><span class="graphic-node gn3">▣</span></div></div>
<div class="stats-grid">
@foreach([
 ['القيمة'=>'properties','label'=>'إجمالي العقارات','icon'=>'⌂','tone'=>'blue'],
 ['القيمة'=>'units','label'=>'إجمالي الوحدات','icon'=>'▦','tone'=>'cyan'],
 ['القيمة'=>'available','label'=>'وحدات متاحة','icon'=>'◈','tone'=>'green'],
 ['القيمة'=>'leases','label'=>'العقود النشطة','icon'=>'▣','tone'=>'violet'],
 ['القيمة'=>'tickets','label'=>'طلبات الصيانة المفتوحة','icon'=>'⚒','tone'=>'orange'],
 ['القيمة'=>'projects','label'=>'مشاريع قيد التنفيذ','icon'=>'◫','tone'=>'blue'],
 ['القيمة'=>'leads','label'=>'فرص تجارية جديدة','icon'=>'◎','tone'=>'cyan']
] as $s)
 <div class="stat-card"><div class="stat-top"><span class="stat-icon {{ $s['tone'] }}">{{ $s['icon'] }}</span><span class="stat-arrow">↗</span></div><strong>{{ number_format($metrics[$s['القيمة']]) }}</strong><span>{{ $s['label'] }}</span></div>
@endforeach
 <div class="stat-card money-card"><span class="stat-icon green">＄</span><strong>{{ \App\Support\Workspace::company()->money($metrics['payments']) }}</strong><span>التحصيل خلال هذا الشهر</span></div>
</div>
<div class="content-grid">
 <section class="panel"><div class="panel-head"><div><span class="eyebrow">CASH FLOW</span><h3>مؤشر التحصيل خلال 6 أشهر</h3></div><span class="tiny-label">{{ \App\Support\Workspace::company()->currency }}</span></div>
 @php($max=max(1,max(array_column($months,'amount'))))
 <div class="bars" role="img" aria-label="رسم توضيحي للتحصيل الشهري">
 @foreach($months as $month)<div class="bar-column"><span class="bar-value">{{ number_format($month['amount'],0) }}</span><div class="bar-track"><div class="bar-fill" style="height:{{ max(4,($month['amount']/$max)*100) }}%"></div></div><small>{{ $month['month'] }}</small></div>@endforeach
 </div></section>
 <section class="panel"><div class="panel-head"><div><span class="eyebrow">PRIORITIES</span><h3>تنبيهات تستحق المتابعة</h3></div></div>
  <div class="priority-list"><div class="priority-row"><div class="priority-icon red">!</div><div><strong>الرصيد المتبقي بالفواتير</strong><p>{{ \App\Support\Workspace::company()->money(max(0,$metrics['unpaid'])) }}</p></div><a href="{{ route('invoices.index') }}">عرض ←</a></div>
  <div class="priority-row"><div class="priority-icon orange">⌁</div><div><strong>عقود تنتهي خلال 45 يوماً</strong><p>{{ $leases->count() }} عقد بحاجة للمتابعة</p></div><a href="{{ route('leases.index') }}">عرض ←</a></div>
  <div class="priority-row"><div class="priority-icon teal">✦</div><div><strong>المساعد المحلي</strong><p>اسأله عن الصيانة والعقود والمشاريع</p></div><a href="{{ route('assistant.index') }}">اسأل ←</a></div></div>
 </section>
</div>
<div class="content-grid">
<section class="panel"><div class="panel-head"><div><span class="eyebrow">RENTALS</span><h3>عقود على وشك الانتهاء</h3></div><a href="{{ route('leases.index') }}" class="text-link">الكل ←</a></div>
@if($leases->isEmpty())<div class="empty-small">لا توجد عقود قريبة الانتهاء.</div>@else
<div class="simple-list">@foreach($leases as $l)<div class="simple-row"><span class="list-avatar">▣</span><div><b>{{ $l->contract_number }}</b><small>{{ $l->unit?->code }} · {{ $l->contact?->name }}</small></div><span class="date-chip">{{ $l->ends_on }}</span></div>@endforeach</div>@endif</section>
<section class="panel"><div class="panel-head"><div><span class="eyebrow">PROJECTS</span><h3>تقدم مشاريع المقاولات</h3></div><a href="{{ route('modules.index','projects') }}" class="text-link">عرض المشاريع ←</a></div>
@if($projects->isEmpty())<div class="empty-small">لا توجد مشاريع مضافة بعد.</div>@else
@foreach($projects as $project)<div class="project-progress"><div><strong>{{ $project->name }}</strong><span>{{ $project->progress }}%</span></div><div class="progress-track"><div style="width:{{ $project->progress }}%"></div></div></div>@endforeach@endif</section>
</div>
@endsection
