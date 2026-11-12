@if ($paginator->hasPages())
<nav class="pagination" role="navigation" aria-label="صفحات النتائج">
 @if ($paginator->onFirstPage())<span class="page-disabled">السابق</span>@else<a href="{{ $paginator->previousPageUrl() }}" rel="prev">السابق</a>@endif
 <span>صفحة {{ $paginator->currentPage() }} من {{ $paginator->lastPage() }} · {{ $paginator->total() }} سجل</span>
 @if ($paginator->hasMorePages())<a href="{{ $paginator->nextPageUrl() }}" rel="next">التالي</a>@else<span class="page-disabled">التالي</span>@endif
</nav>
@endif
