<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Cell\DataValidation;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class AiPostExport implements FromArray, WithEvents, WithHeadings
{
    public function __construct(
        private readonly array $posts,
        private readonly array $projects = [],
        private readonly array $accounts = [],
    ) {
    }

    public function headings(): array
    {
        return ['Project', 'Platform', 'Instagram Content Type', 'Account/Page', 'Content', 'Media URL', 'Thumbnail URL', 'Schedule Date', 'Schedule Time', 'Timezone', 'Status'];
    }

    public function array(): array
    {
        return array_map(fn (array $post) => [
            $post['project'] ?? '',
            $post['platform'] ?? '',
            $post['instagram content type'] ?? '',
            $this->normalizeAccountLabel($post['account/page'] ?? ''),
            $post['content'] ?? '',
            $post['media url'] ?? '',
            $post['thumbnail url'] ?? '',
            $post['schedule date'] ?? '',
            $post['schedule time'] ?? '',
            $post['timezone'] ?? '',
            $post['status'] ?? 'schedule',
        ], $this->posts);
    }

    public function registerEvents(): array
    {
        return [AfterSheet::class => function (AfterSheet $event): void {
            $sheet = $event->sheet->getDelegate();
            $sheet->setTitle('Posts');
            $sheet->freezePane('A2');
            $sheet->getStyle('A1:K1')->getFont()->setBold(true);
            $sheet->getStyle('A1:K1')->getFill()->setFillType('solid')->getStartColor()->setRGB('D9EAF7');
            foreach (range('A', 'K') as $column) $sheet->getColumnDimension($column)->setWidth($column === 'E' ? 48 : 22);
            $sheet->getStyle('H2:H10000')->getNumberFormat()->setFormatCode('yyyy-mm-dd');
            $sheet->getStyle('I2:I10000')->getNumberFormat()->setFormatCode('HH:mm');

            $lists = [$this->projects, ['Facebook', 'Instagram', 'LinkedIn', 'X (Twitter)', 'Pinterest', 'Threads', 'YouTube'], ['Image Post', 'Carousel', 'Reel'], array_map(fn ($account) => $this->normalizeAccountLabel($account), $this->accounts), ['Asia/Kolkata', 'UTC', 'America/New_York', 'Europe/London', 'Australia/Sydney'], ['Draft', 'Pending']];
            $listSheet = $sheet->getParent()->getSheetByName('Lists') ?? $sheet->getParent()->createSheet();
            $listSheet->setTitle('Lists');
            $listSheet->setSheetState(Worksheet::SHEETSTATE_HIDDEN);
            foreach ($lists as $column => $values) foreach ($values as $row => $value) $listSheet->setCellValueByColumnAndRow($column + 1, $row + 1, $value);

            foreach (['A' => 0, 'B' => 1, 'C' => 2, 'D' => 3, 'J' => 4, 'K' => 5] as $target => $listIndex) {
                $source = chr(65 + $listIndex);
                $validation = new DataValidation();
                $validation->setType(DataValidation::TYPE_LIST)->setErrorStyle(DataValidation::STYLE_STOP)->setAllowBlank($target === 'C')->setShowDropDown(true)->setFormula1("Lists!\${$source}\$1:\${$source}\$".max(1, count($lists[$listIndex])));
                $sheet->setDataValidation("{$target}2:{$target}10000", $validation);
            }

            if ($this->posts === []) {
                $sheet->setCellValue('I2', '09:00');
                $sheet->setCellValue('J2', 'Asia/Kolkata');
                $sheet->setCellValue('K2', 'Pending');
            }
        }];
    }

    private function normalizeAccountLabel(mixed $account): string
    {
        return str_replace("\xC3\xA2\xE2\x82\xAC\xE2\x80\x9D", "\xE2\x80\x94", (string) $account);
    }
}
