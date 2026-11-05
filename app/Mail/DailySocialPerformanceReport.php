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

    public function csvContent(): string
    {
        $stream = fopen('php://temp', 'r+');
        fputcsv($stream, $this->report['columns']);

        foreach ($this->report['rows'] as $row) {
            $values = [];
            foreach ($this->report['column_keys'] as $key) {
                $value = $row[$key] ?? null;
                if (is_array($value)) {
                    $value = $value === [] ? '0' : collect($value)->map(fn ($count, $type) => $type.': '.$count)->join(', ');
                } elseif (\App\Support\ReportMetric::isNumericField($key)) {
                    $value = \App\Support\ReportMetric::display($value);
                } else {
                    $value ??= '';
                }
                $values[] = $value;
            }
            fputcsv($stream, $values);
        }

        rewind($stream);
        $csv = stream_get_contents($stream);
        fclose($stream);

        return $csv;
    }

}
