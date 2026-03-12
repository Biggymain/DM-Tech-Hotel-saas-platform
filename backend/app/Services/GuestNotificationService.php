<?php

namespace App\Services;

use App\Models\GuestNotification;
use App\Models\Hotel;
use App\Models\Reservation;
use App\Models\DigitalKey;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * GuestNotificationService
 *
 * Sends booking confirmations and digital key access links via:
 *  - Email (via Laravel Mail / Mailgun / SES)
 *  - WhatsApp (via official Cloud API or Twilio)
 *
 * All sends are logged in guest_notifications for audit/retry.
 */
class GuestNotificationService
{
    public function sendBookingConfirmation(
        Reservation $reservation,
        ?DigitalKey $digitalKey = null,
    ): void {
        $hotel = $reservation->hotel;
        $guest = $reservation->guest;

        if (!$guest) return;

        $payload = $this->buildBookingPayload($hotel, $reservation, $digitalKey);

        // Email
        if ($guest->email) {
            $this->sendEmail($hotel, $reservation, $guest->email, $payload);
        }

        // WhatsApp
        if ($guest->phone) {
            $this->sendWhatsApp($hotel, $reservation, $guest->phone, $payload);
        }
    }

    // ─── Email ────────────────────────────────────────────────────────────────

    private function sendEmail(Hotel $hotel, Reservation $reservation, string $email, array $payload): void
    {
        $log = GuestNotification::create([
            'hotel_id'       => $hotel->id,
            'reservation_id' => $reservation->id,
            'channel'        => 'email',
            'recipient'      => $email,
            'template'       => 'booking_confirmation',
            'status'         => 'pending',
        ]);

        try {
            // Use Laravel's mail system — configured via MAIL_* env vars (Mailgun, SES, SMTP)
            Mail::send('emails.booking_confirmation', $payload, function ($msg) use ($email, $hotel, $reservation) {
                $msg->to($email, $reservation->guest->full_name ?? 'Guest')
                    ->subject("Your Booking at {$hotel->name} — Ref #{$reservation->id}")
                    ->from(config('mail.from.address'), $hotel->name);
            });

            $log->update(['status' => 'sent', 'sent_at' => now()]);
            Log::info("[GuestNotification] Email sent to {$email} for reservation #{$reservation->id}");
        } catch (\Exception $e) {
            $log->update(['status' => 'failed', 'error_message' => $e->getMessage()]);
            Log::error("[GuestNotification] Email failed for reservation #{$reservation->id}: {$e->getMessage()}");
        }
    }

    // ─── WhatsApp (Meta Cloud API) ────────────────────────────────────────────

    private function sendWhatsApp(Hotel $hotel, Reservation $reservation, string $phone, array $payload): void
    {
        $log = GuestNotification::create([
            'hotel_id'       => $hotel->id,
            'reservation_id' => $reservation->id,
            'channel'        => 'whatsapp',
            'recipient'      => $phone,
            'template'       => 'booking_confirmation',
            'status'         => 'pending',
        ]);

        $accessToken = config('services.whatsapp.access_token');
        $phoneNumberId = config('services.whatsapp.phone_number_id');

        if (!$accessToken || !$phoneNumberId) {
            $log->update(['status' => 'failed', 'error_message' => 'WhatsApp credentials not configured.']);
            return;
        }

        try {
            // Clean phone number to E.164 format
            $e164Phone = preg_replace('/[^0-9+]/', '', $phone);
            if (!str_starts_with($e164Phone, '+')) {
                $e164Phone = '+' . ltrim($e164Phone, '0');
            }

            $response = Http::withToken($accessToken)->post(
                "https://graph.facebook.com/v18.0/{$phoneNumberId}/messages",
                [
                    'messaging_product' => 'whatsapp',
                    'to'               => $e164Phone,
                    'type'             => 'template',
                    'template'         => [
                        'name'     => 'booking_confirmation',
                        'language' => ['code' => 'en'],
                        'components' => [
                            [
                                'type' => 'body',
                                'parameters' => [
                                    ['type' => 'text', 'text' => $payload['guest_name']],
                                    ['type' => 'text', 'text' => $hotel->name],
                                    ['type' => 'text', 'text' => $payload['check_in']],
                                    ['type' => 'text', 'text' => $payload['check_out']],
                                    ['type' => 'text', 'text' => "#{$reservation->id}"],
                                    ['type' => 'text', 'text' => $payload['digital_key_section'] ?? 'Your room will be ready upon arrival.'],
                                ],
                            ],
                        ],
                    ],
                ]
            );

            if ($response->successful()) {
                $log->update(['status' => 'sent', 'sent_at' => now()]);
                Log::info("[GuestNotification] WhatsApp sent to {$phone} for reservation #{$reservation->id}");
            } else {
                throw new \Exception($response->body());
            }
        } catch (\Exception $e) {
            $log->update(['status' => 'failed', 'error_message' => $e->getMessage()]);
            Log::error("[GuestNotification] WhatsApp failed for #{$reservation->id}: {$e->getMessage()}");
        }
    }

    // ─── Payload Builder ──────────────────────────────────────────────────────

    private function buildBookingPayload(Hotel $hotel, Reservation $reservation, ?DigitalKey $key): array
    {
        $guest = $reservation->guest;

        $keySection = null;
        if ($key) {
            if ($key->bluetooth_link) {
                $keySection = "🔑 Your digital key is ready: {$key->bluetooth_link}";
            } elseif ($key->key_code) {
                $keySection = "🔑 Your room access PIN: {$key->key_code}";
            } elseif ($key->qr_data) {
                $keySection = "🔑 Please show the QR code emailed separately to open your door.";
            }
        }

        return [
            'hotel_name'           => $hotel->name,
            'hotel_email'          => $hotel->email,
            'guest_name'           => $guest?->full_name ?? 'Guest',
            'reservation_id'       => $reservation->id,
            'room_number'          => $reservation->room?->room_number,
            'check_in'             => $reservation->check_in_date,
            'check_out'            => $reservation->check_out_date,
            'total_amount'         => $reservation->total_amount,
            'digital_key_section'  => $keySection,
            'has_digital_key'      => $key !== null,
            'key_provider'         => $key?->provider,
        ];
    }
}
