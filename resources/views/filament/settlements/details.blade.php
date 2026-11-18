@php
    use App\Filament\Resources\Refunds\RefundResource;
@endphp
<div class="yg-settlement-details" data-settlement-details>
    @if($settlement->note)<p>{{ $settlement->note }}</p>@endif
    @if($settlement->status === \App\Domain\InstructorFinance\SettlementStatus::Cancelled)
        <p>{{ __('settlements.cancelled_reason') }}: {{ $settlement->cancel_reason }}</p>
    @endif

    <table class="w-full text-start">
        <thead><tr>
            <th scope="col">{{ __('ledger.instructor') }}</th>
            <th scope="col">{{ __('settlements.entries') }}</th>
            <th scope="col">{{ __('settlements.amount_due') }}</th>
            <th scope="col">{{ __('settlements.method') }}</th>
            <th scope="col">{{ __('settlements.payout_reference') }}</th>
            <th scope="col">{{ __('settlements.paid_on') }}</th>
        </tr></thead>
        <tbody>
        @foreach($settlement->items as $item)
            <tr data-settlement-item="{{ $item->id }}">
                <td>{{ $item->instructor?->name }}</td>
                <td>{{ $item->entry_count }}</td>
                <td dir="ltr">{{ RefundResource::money($item->amount_minor, $settlement->currency) }}</td>
                <td>{{ $item->payout_method ? __('settlements.method_'.$item->payout_method) : '-' }}</td>
                <td dir="ltr">{{ $item->payout_reference ?? '-' }}</td>
                <td>{{ $item->paid_on?->isoFormat('ll') ?? __('settlements.not_paid') }}</td>
            </tr>
        @endforeach
        </tbody>
    </table>

    @if(! empty($settlement->carried_forward))
        <h4>{{ __('settlements.carried_forward') }}</h4>
        <ul data-carried-forward>
            @foreach($settlement->carried_forward as $row)
                <li>#{{ $row['instructor_id'] }}: {{ __('settlements.carry_'.$row['reason']) }} ({{ RefundResource::money((int) $row['amount_minor'], $settlement->currency) }})</li>
            @endforeach
        </ul>
    @endif
</div>
