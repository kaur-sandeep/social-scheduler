<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class DailySocialPerformanceReport extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public readonly array $report) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Daily Social Media Performance Report - '.$this->report['report_date']);
    }

    public function content(): Content
    {
        return new Content(view: 'mail.daily-social-performance-report');
    }

}
