<?php

namespace App\Services;

use App\Models\Order;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Payment;
use App\Models\Hotel;
use Illuminate\Support\Facades\DB;
use Exception;

class BillingService
{
    /**
     * Generate an invoice automatically when an order is served.
     */
    public function generateInvoiceFromOrder(Order $order): Invoice
    {
        return DB::transaction(function () use ($order) {
            // Ensure no duplicate invoices exist for this order
            if (Invoice::where('order_id', $order->id)->exists()) {
                return Invoice::where('order_id', $order->id)->first();
            }

            $hotel = Hotel::with('currency')->find($order->hotel_id);
            $currencyCode = $hotel->currency ? $hotel->currency->code : 'USD';
            $currencySymbol = $hotel->currency ? $hotel->currency->symbol : '$';

            // Retrieve configuration for sequence numbering
            $lastInvoice = Invoice::where('hotel_id', $order->hotel_id)
                ->orderBy('sequence_number', 'desc')
                ->first();
                
            $sequenceNumber = $lastInvoice ? $lastInvoice->sequence_number + 1 : 1;
            $invoiceNumber = 'INV-' . str_pad($order->hotel_id, 4, '0', STR_PAD_LEFT) . '-' . str_pad($sequenceNumber, 6, '0', STR_PAD_LEFT);

            // Compute subtotal from order items
            $subtotal = 0;
            foreach ($order->items as $item) {
                // We're calculating subtotal directly on what was ordered
                $subtotal += ($item->price * $item->quantity);
            }
            
            // Dynamic tax and service charge logic sourced from SystemSettings
            $taxRate = \App\Models\SystemSetting::getSetting('global_tax_rate', 0.10);
            $serviceChargeRate = \App\Models\SystemSetting::getSetting('global_service_charge', 0.05);
            
            $taxAmount = $subtotal * $taxRate;
            $serviceCharge = $subtotal * $serviceChargeRate;
            $totalAmount = $subtotal + $taxAmount + $serviceCharge;

            $invoice = Invoice::create([
                'hotel_id' => $order->hotel_id,
                'outlet_id' => $order->outlet_id,
                'order_id' => $order->id,
                'invoice_number' => $invoiceNumber,
                'sequence_number' => $sequenceNumber,
                'subtotal' => $subtotal,
                'tax_amount' => $taxAmount,
                'service_charge' => $serviceCharge,
                'discount_amount' => 0,
                'total_amount' => $totalAmount,
                'amount_paid' => 0,
                'currency_code' => $currencyCode,
                'currency_symbol' => $currencySymbol,
                'status' => 'pending',
                'due_date' => now(), // due on receipt for restaurant orders
            ]);

            foreach ($order->items as $item) {
                $description = $item->menuItem ? $item->menuItem->name : 'Custom Item';
                $lineTotal = $item->price * $item->quantity;
                
                $transferLog = \App\Models\TransferLog::where('order_item_id', $item->id)
                    ->where('status', 'success')
                    ->latest()
                    ->first();
                
                InvoiceItem::create([
                    'invoice_id' => $invoice->id,
                    'description' => $description,
                    'quantity' => $item->quantity,
                    'unit_price' => $item->price,
                    'total' => $lineTotal,
                    'transfer_log_id' => $transferLog ? $transferLog->id : null,
                ]);
            }

            return $invoice;
        });
    }

    /**
     * Process a payment against an invoice.
     */
    public function processPayment(Invoice $invoice, float $amount, int $paymentMethodId, int $userId, ?string $reference = null): Payment
    {
        return DB::transaction(function () use ($invoice, $amount, $paymentMethodId, $userId, $reference) {
            $balanceDue = $invoice->total_amount - $invoice->amount_paid;

            if ($amount <= 0) {
                throw new Exception("Payment amount must be greater than zero.");
            }

            if (round($amount, 2) > round($balanceDue, 2)) {
                throw new Exception("Payment amount exceeds balance due.");
            }

            $payment = Payment::create([
                'hotel_id' => $invoice->hotel_id,
                'invoice_id' => $invoice->id,
                'payment_method_id' => $paymentMethodId,
                'type' => 'payment',
                'amount' => $amount,
                'transaction_reference' => $reference,
                'status' => 'completed',
                'processed_by_id' => $userId,
            ]);

            \Illuminate\Support\Facades\Log::info("Processing payment for invoice {$invoice->id}: Amount {$amount}");
            $invoice->amount_paid += $amount;

            $newBalance = $invoice->total_amount - $invoice->amount_paid;
            
            if (round($newBalance, 2) <= 0) {
                $invoice->status = 'paid';
                
                // Payment Kill-Switch
                $sessionToken = request()->cookie('guest_session') ?? request()->header('X-Guest-Session');
                if ($sessionToken) {
                    app(\App\Services\SessionSentryService::class)->revoke($sessionToken);
                } else {
                    // Fallback to active sessions linked to this order's context
                    $session = \App\Models\GuestPortalSession::where('context_type', 'order')
                        ->where('context_id', $invoice->order_id)
                        ->where('status', '!=', 'revoked')
                        ->first();
                    if ($session) {
                        app(\App\Services\SessionSentryService::class)->revoke($session->id);
                    }
                }
            } else {
                $invoice->status = 'partially_paid';
            }

            \Illuminate\Support\Facades\Log::info("Invoice {$invoice->id} status updated to: {$invoice->status}");
            $invoice->save();

            return $payment;
        });
    }

    /**
     * Process a refund for a previously completed payment.
     */
    public function processRefund(Payment $payment, float $amount, int $userId, ?string $notes = null): Payment
    {
        return DB::transaction(function () use ($payment, $amount, $userId, $notes) {
            if ($payment->type !== 'payment') {
                throw new Exception("Can only refund a payment transaction.");
            }

            // Calculate total already refunded
            $previouslyRefunded = Payment::where('invoice_id', $payment->invoice_id)
                ->where('type', 'refund')
                ->where('transaction_reference', "REFUND-{$payment->id}")
                ->where('status', 'completed')
                ->sum('amount');

            $availableToRefund = $payment->amount - $previouslyRefunded;

            if ($amount <= 0) {
                throw new Exception("Refund amount must be greater than zero.");
            }

            if (round($amount, 2) > round($availableToRefund, 2)) {
                throw new Exception("Refund amount exceeds the original payment balance available.");
            }

            $invoice = $payment->invoice;

            $refundRecord = Payment::create([
                'hotel_id' => $invoice->hotel_id,
                'invoice_id' => $invoice->id,
                'payment_method_id' => $payment->payment_method_id,
                'type' => 'refund',
                'amount' => $amount,
                'transaction_reference' => "REFUND-{$payment->id}",
                'status' => 'completed',
                'processed_by_id' => $userId,
                'notes' => $notes,
            ]);

            $invoice->amount_paid -= $amount;

            if ($invoice->amount_paid <= 0) {
                $invoice->status = 'refunded';
            } else {
                $invoice->status = 'partially_refunded'; // If amount paid was fully refunded, status=refunded. Otherwise partially_refunded.
            }
            
            // If there's still balance due but it had been paid before, reset it to partially_paid or pending.
            if ($invoice->amount_paid > 0 && $invoice->amount_paid < $invoice->total_amount) {
                $invoice->status = 'partially_paid';
            } elseif ($invoice->amount_paid <= 0 && $invoice->total_amount > 0) {
                $invoice->status = 'pending';
            }

            $invoice->save();

            return $refundRecord;
        });
    }
}
