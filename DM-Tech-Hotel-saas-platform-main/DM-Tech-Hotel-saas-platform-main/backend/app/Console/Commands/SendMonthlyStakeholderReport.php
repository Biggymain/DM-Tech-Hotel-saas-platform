<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Hotel;
use App\Models\Order;
use App\Models\PaymentTransaction;
use App\Models\Reservation;
use App\Models\LeisureAccessLog;
use App\Mail\MonthlyRevenueReport;
use Illuminate\Support\Facades\Mail;
use Carbon\Carbon;

class SendMonthlyStakeholderReport extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'reports:monthly-stakeholder {--test}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Send monthly revenue and audit reports to hotel stakeholders';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $isTest = $this->option('test');
        $hotels = Hotel::where('is_active', true)->get();

        $startOfMonth = $isTest ? now()->subDays(30) : now()->subMonth()->startOfMonth();
        $endOfMonth = $isTest ? now() : now()->subMonth()->endOfMonth();
        $monthLabel = $startOfMonth->format('F Y');

        foreach ($hotels as $hotel) {
            if (empty($hotel->stakeholder_emails) && !$isTest) {
                $this->warn("No stakeholder emails for {$hotel->name}. Skipping.");
                continue;
            }

            /** @var \App\Models\Hotel $hotel */
            $reportData = $this->generateReportData($hotel, $startOfMonth, $endOfMonth, $monthLabel);
            
            $emails = $hotel->stakeholder_emails ?? [];
            if ($isTest && empty($emails)) {
                $emails = ['admin@dmtech.com']; // Fallback for test
            }

            if (!empty($emails)) {
                $emailsArray = (array) $emails;
                foreach ($emailsArray as $email) {
                    Mail::to($email)->send(new MonthlyRevenueReport($reportData));
                }
                $this->info("Reports sent for {$hotel->name} to " . implode(', ', $emailsArray));
            }
        }

        return 0;
    }

    private function generateReportData(Hotel $hotel, Carbon $start, Carbon $end, string $label)
    {
        // 1. Revenue by Outlet
        $outletsData = [];
        $orders = Order::where('hotel_id', $hotel->id)
            ->whereBetween('created_at', [$start, $end])
            ->where('order_status', 'served')
            ->with('outlet')
            ->get();

        foreach ($orders as $order) {
            $outletName = $order->outlet?->name ?? 'General';
            if (!isset($outletsData[$outletName])) {
                $outletsData[$outletName] = ['revenue' => 0, 'orders' => 0, 'currency' => 'NGN']; 
            }
            $outletsData[$outletName]['revenue'] += $order->total_amount;
            $outletsData[$outletName]['orders']++;
        }

        // 2. Payment Method Split
        $manual = PaymentTransaction::where('hotel_id', $hotel->id)
            ->whereBetween('created_at', [$start, $end])
            ->whereIn('status', ['manual_confirmed'])
            ->sum('amount');

        $gateway = PaymentTransaction::where('hotel_id', $hotel->id)
            ->whereBetween('created_at', [$start, $end])
            ->whereIn('status', ['captured', 'confirmed'])
            ->sum('amount');

        // 3. Occupancy Stats
        $roomNights = Reservation::where('hotel_id', $hotel->id)
            ->whereBetween('check_in', [$start, $end])
            ->count();
        
        $totalRev = Reservation::where('hotel_id', $hotel->id)
            ->whereBetween('check_in', [$start, $end])
            ->sum('total_price');
        
        $adr = $roomNights > 0 ? $totalRev / $roomNights : 0;

        // 4. Triangle of Truth Audit Alerts (Ghost Entries)
        $auditAlerts = [];
        // We look for logs where allow was true
        $logs = LeisureAccessLog::whereHas('outlet', function($q) use ($hotel) {
                $q->where('hotel_id', $hotel->id);
            })
            ->whereBetween('entry_time', [$start, $end])
            ->where('allow', true)
            ->with('outlet')
            ->get();

        foreach ($logs as $log) {
            $isMatched = false;
            // Simplified check for "Ghost Entry"
            if ($log->method === 'MEMBERSHIP') {
                $isMatched = \App\Models\Membership::where('user_id', $log->user_id)->where('status', 'active')->exists();
            } elseif ($log->method === 'RESERVATION') {
                $isMatched = Reservation::where('guest_id', $log->user_id)->where('status', 'checked_in')->exists();
            } elseif ($log->method === 'QR' || $log->method === 'PIN') {
                $isMatched = Order::where('order_number', $log->code)
                    ->where('order_status', 'served')
                    ->whereBetween('created_at', [Carbon::parse($log->entry_time)->subDay(), $log->entry_time])
                    ->exists();
            }

            if (!$isMatched) {
                $auditAlerts[] = [
                    'time' => Carbon::parse($log->entry_time)->format('Y-m-d H:i'),
                    'type' => $log->method,
                    'outlet' => $log->outlet?->name ?? 'Unknown',
                    'code' => $log->code ?? ($log->user_id ? "UID:{$log->user_id}" : "N/A"),
                ];
            }
        }

        return [
            'hotel_name' => $hotel->name,
            'month' => $label,
            'outlets' => $outletsData,
            'payment_methods' => [
                'manual' => $manual,
                'gateway' => $gateway,
            ],
            'occupancy' => [
                'nights' => $roomNights,
                'adr' => $adr,
            ],
            'audit_alerts' => $auditAlerts,
        ];
    }
}
