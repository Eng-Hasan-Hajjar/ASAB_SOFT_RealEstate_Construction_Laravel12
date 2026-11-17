@extends('layouts.app')
@section('title','صلاحيات '.$user->name)
@section('content')
<div class="page-heading"><div><span class="eyebrow">TEAM & ACCESS</span><h1>صلاحيات {{ $user->name }}</h1>
<p>حدّد دور الموظف، وفعّل التقييد بعقارات محددة عند الحاجة، ثم حدّد لكل عقار ما يمكن لهذا الموظف رؤيته أو تعديله.</p></div>
<a class="btn btn-outline" href="{{ route('staff.index') }}">← العودة لفريق العمل</a></div>

<form method="POST" action="{{ route('staff.permissions.update',$user) }}" class="panel edit-panel">
@csrf @method('PUT')
<div class="panel-head"><h3>الدور ونطاق الوصول</h3></div>
<div class="form-grid">
 <div class="form-field">
  <label for="role">الدور</label>
  <select id="role" name="role" {{ $user->id===auth()->id()?'disabled':'' }}>
   <option value="viewer" @selected($user->role==='viewer')>مشاهد (قراءة فقط)</option>
   <option value="agent" @selected($user->role==='agent')>موظف عمليات</option>
   <option value="accountant" @selected($user->role==='accountant')>محاسب</option>
   <option value="manager" @selected($user->role==='manager')>مدير</option>
   <option value="company_admin" @selected($user->role==='company_admin')>مدير الشركة</option>
  </select>
  @if($user->id===auth()->id())
   <input type="hidden" name="role" value="{{ $user->role }}">
   <small class="field-error">لا يمكنك تغيير دورك الخاص من هنا.</small>
  @endif
 </div>
 <div class="form-field wide">
  <label class="checkbox-line"><input type="checkbox" name="scope_properties" value="1" @checked($user->scope_properties)> تقييد هذا الموظف بعقارات محددة فقط</label>
  <small>عند التفعيل، لن يرى هذا الموظف أي عقار أو وحدة لم تُفعّل له «عرض العقار» أدناه — سواء في القوائم أو عند فتح رابط مباشر.</small>
 </div>
</div>
<div class="soft-note"><strong>ملاحظة:</strong> لا يسري التقييد أبداً على دوري «مدير الشركة» و«مدير» — هذان الدوران يحتفظان بالوصول الكامل لجميع العقارات والأسعار دائماً، حتى لو فُعّل مربع التقييد أعلاه.</div>

<section class="v6-detail-section"><h3>صلاحيات كل عقار</h3>
<p class="muted">فعّالة فقط عندما يكون هذا الموظف مقيّداً بعقارات محددة (المربع أعلاه). يمكن منح رؤية سعر الإيجار دون سعر البيع، أو العكس، وبشكل مستقل عن إمكانية التعديل.</p>
<div class="table-scroll"><table class="data-table"><thead><tr>
 <th>العقار</th><th>عرض العقار</th><th>عرض سعر البيع</th><th>عرض سعر الإيجار</th><th>تعديل الأسعار</th>
</tr></thead><tbody>
@forelse($properties as $index=>$property)
@php($grant=$grants->get($property->id))
<tr>
 <td><input type="hidden" name="grants[{{ $index }}][property_id]" value="{{ $property->id }}"><strong>{{ $property->name }}</strong><br><small>{{ $property->city }}</small></td>
 <td><input type="checkbox" name="grants[{{ $index }}][can_view_property]" value="1" @checked($grant?->can_view_property)></td>
 <td><input type="checkbox" name="grants[{{ $index }}][can_view_sale_price]" value="1" @checked($grant?->can_view_sale_price)></td>
 <td><input type="checkbox" name="grants[{{ $index }}][can_view_rent_price]" value="1" @checked($grant?->can_view_rent_price)></td>
 <td><input type="checkbox" name="grants[{{ $index }}][can_edit_prices]" value="1" @checked($grant?->can_edit_prices)></td>
</tr>
@empty
<tr><td colspan="5"><div class="empty-state"><strong>لا توجد عقارات مسجلة بعد</strong><p>أضف عقاراً أولاً من إدارة العقارات، ثم عد إلى هذه الصفحة لمنح الموظف صلاحياته.</p></div></td></tr>
@endforelse
</tbody></table></div>
</section>
<div class="form-actions"><button class="btn btn-primary" type="submit">✓ حفظ الصلاحيات</button><a href="{{ route('staff.index') }}" class="btn btn-outline">إلغاء</a></div>
</form>
@endsection
