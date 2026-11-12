@extends('layouts.app')
@section('title',($item?'تعديل ':'إضافة ').$def['singular'])
@section('content')
<div class="page-heading"><div><span class="eyebrow">{{ $def['title'] }}</span><h1>{{ $item?'تعديل':'إضافة' }} {{ $def['singular'] }}</h1><p>املأ الحقول بدقة. الحقول المشار إليها بنجمة مطلوبة.</p></div><a class="btn btn-outline" href="{{ route('modules.index',$module) }}">← العودة للقائمة</a></div>
<form class="panel edit-panel" method="POST" action="{{ $item?route('modules.update',[$module,$item->id]):route('modules.store',$module) }}">@csrf @if($item)@method('PUT')@endif
<div class="panel-head"><h3>البيانات الأساسية</h3><span class="tiny-label">{{ $def['singular'] }}</span></div>
<div class="form-grid">
@foreach($def['fields'] as $name=>$field)
@php($val=old($name,$item?->{$name}??($prefill[$name]??null)))
<div class="form-field {{ $field['type']==='textarea'?'wide':'' }}"><label for="{{ $name }}">{{ $field['label'] }} @if($field['required']??false)<span class="required">*</span>@endif</label>
@if($field['type']==='textarea')
<textarea id="{{ $name }}" name="{{ $name }}" rows="4" {{ ($field['required']??false)?'required':'' }}>{{ $val }}</textarea>
@elseif(in_array($field['type'],['select','relation'],true))
<select id="{{ $name }}" name="{{ $name }}" {{ ($field['required']??false)?'required':'' }}>
<option value="">— اختر —</option>
@foreach(($field['type']==='select'?$field['options']:$options[$name]) as $key=>$label)
<option value="{{ $key }}" @selected((string)$val===(string)$key)>{{ $label }}</option>
@endforeach
</select>
@else
<input id="{{ $name }}" name="{{ $name }}" type="{{ match($field['type']){'date'=>'date','money','number','progress'=>'number','email'=>'email',default=>'text'} }}" value="{{ $val }}" @if(in_array($field['type'],['money','number','progress'],true)) step="{{ $field['type']==='money'?'0.001':($field['type']==='progress'?'1':'any') }}" @endif @if($field['type']==='progress') min="0" max="100" @endif {{ ($field['required']??false)?'required':'' }}>
@endif
@error($name)<small class="field-error">{{ $message }}</small>@enderror
</div>
@endforeach
</div>
<div class="form-actions"><button class="btn btn-primary" type="submit">✓ حفظ البيانات</button><a href="{{ route('modules.index',$module) }}" class="btn btn-outline">إلغاء</a></div></form>
@endsection
