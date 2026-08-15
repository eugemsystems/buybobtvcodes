<?php

namespace App\Observers;

use App\Enums\TransactionStatus;
use App\Jobs\FirePartnerWebhookJob;
use App\Models\Setting;
use App\Models\Transaction;

class TransactionObserver
{
    public function updated(Transaction $transaction): void
    {
        if (! $transaction->wasChanged('status')) {
            return;
        }

        if ($transaction->status !== TransactionStatus::Completed) {
            return;
        }

        $this->recordCommission($transaction);

        $partnerData = $transaction->partner_data ?? [];

        if (empty($partnerData['reference'])) {
            return;
        }

        $webhookUrl = Setting::get('webhook_url', '');

        if (! $webhookUrl) {
            return;
        }

        FirePartnerWebhookJob::dispatch($webhookUrl, $transaction->id);
    }

    /**
     * Snapshot the commission rate and amount at the moment a sale completes, so a
     * later change to the rate never rewrites already-earned historical commission.
     */
    private function recordCommission(Transaction $transaction): void
    {
        if ($transaction->commission_amount !== null) {
            return;
        }

        $store = $transaction->store;
        $rate = $store?->effectiveCommissionRate() ?? 0.0;

        $transaction->commission_rate_applied = $rate;
        $transaction->commission_amount = round((float) $transaction->amount * $rate / 100, 2);
        $transaction->saveQuietly();
    }
}
