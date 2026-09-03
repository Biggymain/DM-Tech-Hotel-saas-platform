<x-mail::message>
# Monthly Audit Report: {{ $hotel_name }}
**Month:** {{ $month }}

Here is the operational summary for your property.

## 💰 Revenue by Outlet
| Outlet | Revenue | Orders |
| :--- | :--- | :--- |
@foreach($outlets as $name => $stats)
| {{ $name }} | {{ $stats['currency'] }} {{ number_format($stats['revenue'], 2) }} | {{ $stats['orders'] }} |
@endforeach

## 💳 Payment Method Split
| Method | Amount |
| :--- | :--- |
| **Manual Transfers** | {{ number_format($payment_methods['manual'], 2) }} |
| **Gateway Payments** | {{ number_format($payment_methods['gateway'], 2) }} |

## 🏨 Occupancy Stats
- **Total Room Nights Sold:** {{ $occupancy['nights'] }}
- **Avg Daily Rate (ADR):** {{ number_format($occupancy['adr'], 2) }}

@if(count($audit_alerts) > 0)
## ⚠️ 'Triangle of Truth' Audit Alerts
<x-mail::panel>
**Ghost Entries Found:** These are hardware-verified entries with no corresponding order/payment record.
<ul>
@foreach($audit_alerts as $alert)
    <li><strong>{{ $alert['time'] }}</strong>: {{ $alert['type'] }} access at <strong>{{ $alert['outlet'] }}</strong> (Code: <code>{{ $alert['code'] }}</code>)</li>
@endforeach
</ul>
</x-mail::panel>
@else
## ✅ Audit Integrity Verified
No 'Ghost Entries' or manual reconciliation mismatches were found this month.
@endif

<x-mail::button :url="config('app.url') . '/dashboard/reports'">
View Full Audit Dashboard
</x-mail::button>

Thanks,<br>
**DM-Tech Billing Engine**
</x-mail::message>
