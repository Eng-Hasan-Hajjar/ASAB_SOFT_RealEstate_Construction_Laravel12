@props(['url','label','icon'=>'building','active'=>false,'external'=>false])
<a class="nav-link {{ $active?'active':'' }}" href="{{ $url }}" title="{{ $label }}" aria-label="{{ $label }}" @if($active) aria-current="page" @endif @if($external) target="_blank" rel="noopener noreferrer" @endif>
 <span class="nav-emoji"><x-ops-icon :name="$icon" /></span>
 <span class="ops-nav-text">{{ $label }}</span>
</a>
