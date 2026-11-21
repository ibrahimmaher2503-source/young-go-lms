<x-filament-panels::page>
    @include('filament.ai.styles')

    @php
        $money = static fn (?int $minor, ?string $currency): string => $minor === null ? '-' : number_format($minor / 100, 2).($currency ? ' '.$currency : '');
    @endphp

    <div class="yg-rv" data-payment-reconciliation>
        <section class="yg-rv-card" aria-label="{{ __('payment_reconciliation.last_run') }}">
            <p class="yg-rv-meta">{{ __('payment_reconciliation.report_only') }}</p>
            @if($run)
                <dl class="yg-ai-tiles" data-reconciliation-run>
                    <div class="yg-ai-tile"><dt>{{ __('payment_reconciliation.run_date') }}</dt><dd class="yg-ai-num">{{ $run->run_date->isoFormat('ll') }}</dd></div>
                    <div class="yg-ai-tile"><dt>{{ __('payment_reconciliation.transactions') }}</dt><dd class="yg-ai-num">{{ $run->transactions_seen }}</dd></div>
                    <div class="yg-ai-tile" data-tone="success"><dt>{{ __('payment_reconciliation.matched') }}</dt><dd class="yg-ai-num">{{ $run->matched }}</dd></div>
                    <div class="yg-ai-tile" @if($openCount > 0) data-tone="danger" @endif><dt>{{ __('payment_reconciliation.open') }}</dt><dd class="yg-ai-num" data-open-count>{{ $openCount }}</dd></div>
                </dl>
            @else
                <p data-reconciliation-never>{{ __('payment_reconciliation.never_run') }}</p>
            @endif
        </section>

        <section class="yg-rv-card" aria-label="{{ __('payment_reconciliation.mismatches') }}">
            <div class="yg-ai-row">
                <h3 class="yg-rv-title">{{ __('payment_reconciliation.mismatches') }}</h3>
                <div class="yg-ai-field">
                    <label for="recon-filter">{{ __('payment_reconciliation.filter') }}</label>
                    <x-filament::input.wrapper>
                        <x-filament::input.select id="recon-filter" wire:model.live="filter">
                            <option value="open">{{ __('payment_reconciliation.status.open') }}</option>
                            <option value="reviewed">{{ __('payment_reconciliation.status.reviewed') }}</option>
                            <option value="all">{{ __('payment_reconciliation.all') }}</option>
                        </x-filament::input.select>
                    </x-filament::input.wrapper>
                </div>
            </div>

            @if($mismatches->isEmpty())
                <p data-mismatch-empty>{{ __('payment_reconciliation.empty') }}</p>
            @else
                <div class="yg-ai-scroll" tabindex="0">
                    <table class="yg-ai-table" data-mismatch-table>
                        <thead><tr>
                            <th scope="col">{{ __('payment_reconciliation.found') }}</th>
                            <th scope="col">{{ __('payment_reconciliation.type') }}</th>
                            <th scope="col">{{ __('payment_reconciliation.reason') }}</th>
                            <th scope="col">{{ __('payment_reconciliation.reference') }}</th>
                            <th scope="col">{{ __('payment_reconciliation.order') }}</th>
                            <th scope="col">{{ __('payment_reconciliation.gateway_amount') }}</th>
                            <th scope="col">{{ __('payment_reconciliation.local_amount') }}</th>
                            <th scope="col">{{ __('payment_reconciliation.state') }}</th>
                            @if($canReview)<th scope="col"><span class="sr-only">{{ __('payment_reconciliation.actions') }}</span></th>@endif
                        </tr></thead>
                        <tbody>
                        @foreach($mismatches as $mismatch)
                            <tr data-mismatch="{{ $mismatch->id }}" data-status="{{ $mismatch->status->value }}">
                                <td class="yg-ai-num"><x-ui.ltr>{{ $mismatch->created_at->isoFormat('lll') }}</x-ui.ltr></td>
                                <td>{{ __('payment_reconciliation.types.'.$mismatch->type->value) }}</td>
                                <td>{{ __('payment_reconciliation.reasons.'.$mismatch->reason) }}</td>
                                <td class="yg-ai-num">{{ $mismatch->attempt_reference ?? '-' }}<br><span class="yg-rv-meta">{{ $mismatch->provider_transaction_id }}</span></td>
                                <td class="yg-ai-num"><x-ui.ltr>{{ $mismatch->order?->reference ?? '-' }}</x-ui.ltr></td>
                                <td class="yg-ai-num">{{ $money($mismatch->gateway_amount_minor, $mismatch->currency) }}</td>
                                <td class="yg-ai-num">{{ $money($mismatch->local_amount_minor, $mismatch->currency) }}</td>
                                <td><span class="yg-rv-badge">{{ __('payment_reconciliation.status.'.$mismatch->status->value) }}</span>
                                    @if($mismatch->reviewer)<span class="yg-rv-meta">{{ $mismatch->reviewer->name }}</span>@endif</td>
                                @if($canReview)
                                    <td>
                                        @if($mismatch->status === \App\Domain\Commerce\ReconciliationMismatchStatus::Open)
                                            <button type="button" class="yg-rv-btn" wire:click="markReviewed({{ $mismatch->id }})" wire:loading.attr="disabled">{{ __('payment_reconciliation.mark_reviewed') }}</button>
                                        @endif
                                    </td>
                                @endif
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
                <p class="yg-rv-meta">{{ __('payment_reconciliation.limit_note', ['limit' => $limit]) }}</p>
            @endif
        </section>
    </div>
</x-filament-panels::page>
