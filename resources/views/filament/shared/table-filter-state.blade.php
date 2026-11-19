{{-- Marks how many filters are active so CSS can hide the empty "0" badge on the filter trigger. --}}
<span class="yg-filter-state" data-active-filters="{{ $table->getActiveFiltersCount() }}" hidden></span>
