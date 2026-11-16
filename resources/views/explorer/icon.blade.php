<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.65" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
@switch($icon ?? 'building')
@case('shop')<path d="M3 10l2-6h14l2 6v2a3 3 0 0 1-5 2 3 3 0 0 1-4 0 3 3 0 0 1-4 0 3 3 0 0 1-5-2zM5 15v6h14v-6M9 21v-5h6v5"/>@break
@case('villa')@case('apartment')<path d="M3 11l9-8 9 8M5 10v11h14V10M10 21v-6h4v6"/>@break
@case('warehouse')<path d="M3 21V8l9-5 9 5v13M7 21V10h10v11M7 14h10M7 17h10"/>@break
@case('office')<path d="M3 20h18M5 20v-8h14v8M8 12V5h8v7M9 16h6"/>@break
@case('pin')<path d="M20 10c0 6-8 11-8 11S4 16 4 10a8 8 0 1 1 16 0z"/><circle cx="12" cy="10" r="2.5"/>@break
@case('layers')<path d="M12 3l9 5-9 5-9-5 9-5zM3 12l9 5 9-5M3 16l9 5 9-5"/>@break
@default<path d="M3 21h18M5 21V4h10v17m0-12h4v12M8 8h4m-4 4h4m-4 4h4"/>
@endswitch
</svg>
