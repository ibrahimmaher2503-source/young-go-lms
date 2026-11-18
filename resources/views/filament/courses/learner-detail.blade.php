<ul class="space-y-2">
    @foreach($detail as $row)
        <li class="flex flex-wrap justify-between gap-2">
            <span>{{ $row['title'] }}</span>
            <span>{{ __('courses.lesson_status.'.$row['status']) }} · {{ $row['percent'] }}%@if($row['last_at']) · {{ $row['last_at'] }}@endif</span>
        </li>
    @endforeach
</ul>
