<?php

namespace App\Notifications;

use App\Models\PostImport;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class PostImportCompletedNotification extends Notification
{
    use Queueable;
    public function __construct(private readonly PostImport $import) {}
    public function via(object $notifiable): array { return ['database']; }
    public function toArray(object $notifiable): array { return ['message' => "Import #{$this->import->id} completed: {$this->import->successful_rows} posts imported, {$this->import->failed_rows} failed.", 'import_id' => $this->import->id]; }
}
