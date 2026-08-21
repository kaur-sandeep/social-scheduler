<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;

class AiPostExport implements FromArray, WithHeadings
{
    public function __construct(
        private readonly array $posts
    ) {
    }

    public function headings(): array
    {
         return [
            'Project',
            'Platform',
            'Instagram Content Type',
            'Account/Page',
            'Content',
            'Media URL',
            'Schedule Date',
            'Schedule Time',
            'Timezone',
            'Status',
        ];
    }

    public function array(): array
    {
        return array_map(function (array $post) {
            return [
                $post['project'] ?? '',
                $post['platform'] ?? '',
                $post['instagram content type'] ?? '',
                $post['account/page'] ?? '',
                $post['content'] ?? '',

                // IMPORTANT:
                // AI must never provide media.
                // User adds this later.
                '',

                $post['schedule date'] ?? '',
                $post['schedule time'] ?? '',
                $post['timezone'] ?? '',
                $post['status'] ?? 'schedule',
            ];
        }, $this->posts);
    }
}