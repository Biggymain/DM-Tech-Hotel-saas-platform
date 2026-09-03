<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use App\Models\Folio;
use App\Models\ProcessedWebhook;
use App\Models\AuditLog;
use Illuminate\Support\Facades\DB;

class AuditFolioIntegrityJob implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new job instance.
     */
    public function __construct()
    {
        //
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        // Fetch all folios that have generated payments
        // We do this by cross-referencing processed webhooks through payment_transactions
        Folio::with(['payments'])->chunk(100, function ($folios) {
            foreach ($folios as $folio) {
                if ($folio->status === 'suspended_tamper_detected') continue;

                $totalGatewayConfirmed = 0;
                $gatewayReferences = [];
                
                foreach ($folio->payments as $payment) {
                    if ($payment->status === 'captured' && !empty($payment->gateway_transaction_id)) {
                        $gatewayReferences[] = $payment->gateway_transaction_id;
                    }
                }

                if (empty($gatewayReferences)) continue;

                // Gateway truth anchor querying
                $totalGatewayConfirmed = ProcessedWebhook::whereIn('provider_reference', $gatewayReferences)
                    ->where('status', 'captured')
                    ->sum('amount');

                // Compare ledger paid against external gateway confirmed
                // Allowed variance delta is 0 for SaaS strictness. 
                // But wait! Manual cash payments exist.
                // We should only compare gateway payments sum directly against gateway recorded sum.
                $folioGatewaySum = $folio->payments()->where('status', 'captured')
                    ->whereNotNull('gateway_transaction_id')
                    ->sum('amount');

                if (round((float) $totalGatewayConfirmed, 2) !== round((float) $folioGatewaySum, 2)) {
                    // SUSPEND FOLIO
                    $folio->update(['status' => 'suspended_tamper_detected']);

                    AuditLog::create([
                        'hotel_id' => $folio->hotel_id,
                        'change_type' => 'security_alert',
                        'entity_type' => 'folio',
                        'entity_id' => $folio->id,
                        'source' => 'nightly_anchor',
                        'reason' => "Gateway Anchor Mismatch! DB Gateway Payments sum ($folioGatewaySum) != Webhook Gateway Truth ($totalGatewayConfirmed).",
                        'new_values' => ['severity' => 20]
                    ]);
                    
                    Log::critical("TAMPER DETECTED BY NIGHTLY ANCHOR: Folio #{$folio->id} suspended.");
                }
            }
        });
    }
}
