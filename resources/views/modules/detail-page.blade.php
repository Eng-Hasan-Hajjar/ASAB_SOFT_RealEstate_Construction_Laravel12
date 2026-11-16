@extends('layouts.app')
@section('title','تفاصيل '.$def['singular'])
@section('content')
<div class="page-heading"><div><span class="eyebrow">{{ $def['title'] }}</span><h1>{{ $item->name ?? $item->code ?? $item->title ?? 'تفاصيل السجل' }}</h1></div><a class="btn btn-outline" href="{{ route('modules.index',$module) }}">العودة للقائمة</a></div>
<section class="panel detail-panel">@include('modules.preview')</section>
@endsection
