<?php

namespace App\Exports;

use App\Support\PmecSubmissionDefaults;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;

class PmecSubmissionExport implements FromArray, WithHeadings, WithEvents
{
    /**
     * @param  array<int, array<int, string|float|null>>  $rows
     */
    public function __construct(
        private readonly array $rows,
        private readonly float $betrgTotal,
    ) {}

    public function headings(): array
    {
        return PmecSubmissionDefaults::excelHeadings();
    }

    public function array(): array
    {
        return $this->rows;
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event): void {
                $sheet = $event->sheet->getDelegate();
                $lastRow = $sheet->getHighestRow() + 1;

                $sheet->setCellValue('A'.$lastRow, 'Total');
                $sheet->setCellValue('E'.$lastRow, $this->betrgTotal);

                $sheet->getStyle('A'.$lastRow.':J'.$lastRow)->getFont()->setBold(true);
                $sheet->getStyle('A'.$lastRow.':J'.$lastRow)
                    ->getFill()
                    ->setFillType(Fill::FILL_SOLID)
                    ->getStartColor()
                    ->setARGB('FFE2E8F0');
            },
        ];
    }
}
