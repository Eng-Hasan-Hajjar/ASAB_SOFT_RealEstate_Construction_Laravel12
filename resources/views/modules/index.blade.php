@extends('layouts.app')
@section('title', $def['title'])
@section('content')
<div class="page-heading">
  <div>
    <span class="eyebrow">ASAB OPERATIONS / {{ strtoupper($module) }}</span>
    <h1>{{ $def['title'] }}</h1>
    <p>{{ $def['description'] }} · عدد النتائج {{ $items->total() }}</p>
  </div>
  @if(\App\Support\ModuleRegistry::canWrite(\App\Support\Workspace::role(), $module))
    <a class="btn btn-primary" href="{{ route('modules.create', $module) }}">+ إضافة {{ $def['singular'] }}</a>
  @endif
</div>

<section class="panel smart-filters" aria-label="الفلترة المتقدمة">
  <div class="smart-filters-header">
    <div><span class="eyebrow">ADVANCED SEARCH</span><h2>البحث والفلاتر المتقدمة</h2></div>
    <a class="btn btn-outline" href="{{ route('modules.index', $module) }}">إعادة ضبط الفلاتر</a>
  </div>
  <form method="GET" action="{{ route('modules.index', $module) }}" class="filters-grid">
    <label class="filter-control"><span>البحث النصي</span><input type="search" name="q" value="{{ $search }}" maxlength="150" placeholder="الاسم، الرقم، الوصف..."></label>
    @foreach($filterOptions as $column => $options)
      <label class="filter-control"><span>{{ $def['fields'][$column]['label'] }}</span>
        <select name="f[{{ $column }}]">
          <option value="">الكل</option>
          @foreach($options as $value => $label)
            <option value="{{ $value }}" @selected((string)($selectedFilters[$column] ?? '') === (string)$value)>{{ $label }}</option>
          @endforeach
        </select>
      </label>
    @endforeach
    @if($module === 'properties')
      <label class="filter-control"><span>إشغال وحدات العقار</span><select name="occupancy">
        <option value="">جميع حالات الإشغال</option>
        @foreach(['all_available'=>'جميع الوحدات متاحة','all_occupied'=>'جميع الوحدات مؤجرة','mixed'=>'متاح ومؤجر معاً','has_available'=>'يحتوي وحدات متاحة','has_occupied'=>'يحتوي وحدات مؤجرة','no_units'=>'لا توجد وحدات'] as $value => $label)
          <option value="{{ $value }}" @selected(($data['occupancy'] ?? '') === $value)>{{ $label }}</option>
        @endforeach
      </select></label>
    @endif
    @if($module === 'contacts')
      <label class="filter-control"><span>العقار المرتبط بعقد</span><select name="contact_property"><option value="">جميع العقارات</option>
        @foreach($contactProperties as $pid => $pname)
          <option value="{{ $pid }}" @selected((string)($data['contact_property'] ?? '') === (string)$pid)>{{ $pname }}</option>
        @endforeach
      </select></label>
    @endif
    @if($module === 'contacts')
      <label class="filter-control"><span>وجود عقود للمستأجر</span><select name="contact_lease">
        <option value="">الكل</option>
        <option value="active" @selected(($data['contact_lease'] ?? '') === 'active')>له عقد نشط</option>
        <option value="not_current" @selected(($data['contact_lease'] ?? '') === 'not_current')>لا عقد حالي</option>
        <option value="former" @selected(($data['contact_lease'] ?? '') === 'former')>مستأجر سابق</option>
        <option value="upcoming" @selected(($data['contact_lease'] ?? '') === 'upcoming')>له عقد مجدول</option>
        <option value="any" @selected(($data['contact_lease'] ?? '') === 'any')>له عقد مسجّل</option>
        <option value="historical" @selected(($data['contact_lease'] ?? '') === 'historical')>له ارتباط في السجل التاريخي</option>
        <option value="none" @selected(($data['contact_lease'] ?? '') === 'none')>لا يوجد عقد مسجل</option>
      </select></label>
    @endif
    @php($advancedFields=\App\Support\AssetFilters::fields($module))
    @if($advancedFields)
    <details class="v6-advanced" @if(collect($data)->keys()->contains(fn($key)=>str_starts_with($key,'asset_') && filled($data[$key]??null))) open @endif>
     <summary>خيارات بحث إضافية وترتيب</summary>
     <div class="filters-grid">
      @foreach($advancedFields as $key=>$label)
      <label class="filter-control"><span>{{ $label }}</span>
       @if($opts=\App\Support\AssetFilters::options($module)[$key]??null)
       <select name="{{ $key }}"><option value="">الكل</option>@foreach($opts as $value=>$optionLabel)<option value="{{ $value }}" @selected(($data[$key]??null)===$value)>{{ $optionLabel }}</option>@endforeach</select>
       @else
       <input name="{{ $key }}" type="{{ preg_match('/(?:_min|_max|_floor)$/',$key)?'number':'search' }}" @if(preg_match('/(?:_min|_max|_floor)$/',$key)) step="{{ $key==='asset_floor'?'1':'0.001' }}" @endif maxlength="120" value="{{ $data[$key]??'' }}">
       @endif
      </label>
      @endforeach
      <label class="filter-control"><span>ترتيب النتائج</span><select name="asset_sort">@foreach(\App\Support\AssetFilters::sorts($module) as $key=>$label)<option value="{{ $key }}" @selected(($data['asset_sort']??'recent')===$key)>{{ $label }}</option>@endforeach</select></label>
     </div>
    </details>
    @endif
    @if(count($dateFields))
      <label class="filter-control"><span>حقل التاريخ</span><select name="date_field">
        @foreach($dateFields as $col => $field)<option value="{{ $col }}" @selected($dateField === $col)>{{ $field['label'] }}</option>@endforeach
      </select></label>
      <label class="filter-control"><span>من تاريخ</span><input type="date" name="date_from" value="{{ $data['date_from'] ?? '' }}"></label>
      <label class="filter-control"><span>إلى تاريخ</span><input type="date" name="date_to" value="{{ $data['date_to'] ?? '' }}"></label>
    @endif
    @if(count($moneyFields) && !in_array($module,['properties','units'],true))
      <label class="filter-control"><span>حقل المبلغ / العدد</span><select name="money_field">
        @foreach($moneyFields as $col => $field)<option value="{{ $col }}" @selected($moneyField === $col)>{{ $field['label'] }}</option>@endforeach
      </select></label>
      <label class="filter-control"><span>من قيمة</span><input type="number" step="0.001" min="0" name="money_min" value="{{ $data['money_min'] ?? '' }}"></label>
      <label class="filter-control"><span>إلى قيمة</span><input type="number" step="0.001" min="0" name="money_max" value="{{ $data['money_max'] ?? '' }}"></label>
    @endif
    <div class="filter-control filter-submit"><button type="submit" class="btn btn-primary">تطبيق الفلاتر</button></div>
  </form>
  <div class="filters-result">تظهر حالة الإشغال وفق سجلات النظام الحالية فقط؛ الوحدات المستوردة وغير المعتمدة لا يُفترض أنها مشغولة أو متاحة.</div>
</section>

<section class="panel table-panel">
  <div class="table-toolbar"><strong>النتائج</strong><span class="soft-label">{{ $items->total() }} سجل</span></div>
  <div class="table-scroll"><table class="data-table"><thead><tr>
      <th>الرقم</th>
      @foreach($def['columns'] as $column)
        <th>{{ $def['fields'][$column]['label'] }}</th>
      @endforeach
      @if($module === 'properties')<th>إشغال الوحدات</th>@elseif($module==='contacts')<th>العلاقة الحالية</th>@endif
      <th>الإجراءات</th>
    </tr></thead><tbody>
    @forelse($items as $item)
      @php($rowState=match($module){'units'=>$item->occupancy_status,'properties'=>$item->status,'contacts'=>$item->active_leases_exists?'active':'inactive',default=>''})
      <tr class="{{ $rowState ? 'v6-row v6-state-'.$rowState : '' }}">
        <td class="id-cell">#{{ $item->id }}</td>
        @foreach($def['columns'] as $column)
          @php($field=$def['fields'][$column])
          <td>
            @if($field['type']==='relation')
              {{ $displayOptions[$column][$item->{$column}] ?? '—' }}
            @elseif($field['type']==='select')
              <span class="status-pill">{{ $module==='units' && $column==='status' ? \App\Models\Unit::statusLabels()[$item->occupancy_status] : ($field['options'][(string)$item->{$column}] ?? $item->{$column} ?? '—') }}</span>
            @elseif($field['type']==='money')
              <span class="money-text" dir="ltr">{{ \App\Support\Workspace::company()->money($item->{$column}) }}</span>
            @elseif($field['type']==='progress')
              <span class="progress-mini">{{ $item->{$column} }}%</span>
            @else
              {{ \Illuminate\Support\Str::limit((string)$item->{$column},65) ?: '—' }}
            @endif
          </td>
        @endforeach
        @if($module === 'properties')
          <td><small>الإجمالي {{ $item->units_count }} · متاحة {{ $item->available_units_count }} · مؤجرة {{ $item->occupied_units_count }}</small></td>
        @elseif($module==='contacts')
          <td><span class="status-pill">{{ $item->active_leases_exists ? 'مرتبط بعقد حالي' : 'لا عقد حالي' }}</span></td>
        @endif
        <td class="actions-cell">
          <a href="{{ route('modules.preview',[$module,$item->id]) }}" class="table-action view-detail-btn" data-modal-url="{{ route('modules.preview',[$module,$item->id]) }}" data-modal-title="تفاصيل {{ $def['singular'] }} #{{ $item->id }}">عرض التفاصيل</a>
          @if(in_array($module,['projects','properties'],true))
            <a class="table-action" href="{{ route('modules.show',[$module,$item->id]) }}">صفحة موسعة</a>
          @endif
          @if(\App\Support\ModuleRegistry::canWrite(\App\Support\Workspace::role(),$module))
            <a class="table-action" href="{{ route('modules.edit',[$module,$item->id]) }}">تعديل</a>
            @if(in_array(\App\Support\Workspace::role(),['company_admin','manager'],true))
              <form method="POST" action="{{ route('modules.destroy',[$module,$item->id]) }}" class="inline-form js-confirm" data-confirm="تأكيد حذف السجل؟ لا يمكن حذف السجلات المرتبطة بعقود أو حركات مالية.">
                @csrf
                @method('DELETE')
                <button type="submit" class="table-action danger-text">حذف</button>
              </form>
            @endif
          @endif
        </td>
      </tr>
    @empty
      <tr><td colspan="{{ count($def['columns']) + (in_array($module,['properties','contacts'],true) ? 3 : 2) }}"><div class="empty-state"><strong>لا توجد نتائج مطابقة</strong><p>غيّر معايير البحث أو أعد ضبط الفلاتر.</p></div></td></tr>
    @endforelse
    </tbody></table></div>
  {{ $items->links() }}
</section>

@include('modules.detail-dialog')
@endsection
