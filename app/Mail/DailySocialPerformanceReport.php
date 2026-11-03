<?php

namespace App\Mail;

use App\Support\ReportMetric;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
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

    public function attachments(): array
    {
        return [Attachment::fromData(fn () => $this->csvContent(), 'daily-social-performance-'.$this->report['report_date'].'.csv')
            ->withMime('text/csv; charset=UTF-8')];
    }

    public function csvContent(): string
    {
        $stream = fopen('php://temp', 'r+');
        fputcsv($stream, $this->report['columns']);
        foreach ($this->report['rows'] as $row) {
            $values = array_map(function (string $column) use ($row) {
                if (ReportMetric::isNumericField($column)) return ReportMetric::display($row[$column] ?? null);
                $value = $row[$column] ?? null;
                return $value === null ? '' : $value;
            }, $this->report['column_keys']);
            foreach ($values as &$value) {
                if (is_array($value)) $value = implode('; ', array_map(fn ($key, $count) => $key.': '.$count, array_keys($value), $value));
                if (is_string($value) && preg_match('/^[=+@\-\t\r]/', $value)) $value = "'".$value;
            }
            unset($value);
            fputcsv($stream, $values);
        }
        rewind($stream);
        $csv = "\xEF\xBB\xBF".stream_get_contents($stream);
        fclose($stream);
        return $csv;
    }
}
