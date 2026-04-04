<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class MonthlyRevenueReport extends Mailable
{
    use Queueable, SerializesModels;

    public $data;

    /**
     * Create a new message instance.
     */
    public function __construct(array $data)
    {
        $this->data = $data;
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "Stakeholder Monthly Audit Report - " . ($this->data['hotel_name'] ?? 'DM-Tech'),
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            markdown: 'emails.reports.monthly_revenue',
            with: [
                'hotel_name' => $this->data['hotel_name'],
                'month' => $this->data['month'],
                'outlets' => $this->data['outlets'],
                'payment_methods' => $this->data['payment_methods'],
                'occupancy' => $this->data['occupancy'],
                'audit_alerts' => $this->data['audit_alerts'],
            ]
        );
    }

    /**
     * Get the attachments for the message.
     *
     * @return array<int, \Illuminate\Mail\Mailables\Attachment>
     */
    public function attachments(): array
    {
        return [];
    }
}
