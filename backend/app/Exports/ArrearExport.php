<?php

namespace App\Exports;

use Illuminate\Contracts\Support\Responsable;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithColumnFormatting;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class ArrearExport implements FromCollection, WithHeadings, WithStyles, WithColumnFormatting, ShouldAutoSize, WithTitle, Responsable
{
    use Exportable;

    public function __construct(private Collection $arrears)
    {
    }

    public function title(): string
    {
        return 'Hasil Penagihan';
    }

    public function headings(): array
    {
        return [
            'No',
            'No Sambungan',
            'Nama Pelanggan',
            'Jumlah Bulan Tunggakan',
            'Petugas',
            'Status Kunjungan',
            'Hasil Kunjungan',
            'Keterangan',
            'Tanggal Kunjungan',
            'Foto Bukti / Path',
        ];
    }

    public function collection(): Collection
    {
        return $this->arrears->map(function ($arrear, $index) {
            $visit = $arrear->visit;

            return [
                $index + 1,
                $arrear->customer->no_sambungan,
                $arrear->customer->nama,
                $arrear->jumlah_bulan_tunggakan,
                $arrear->petugas?->name ?? '-',
                $this->statusLabel($arrear->status),
                $visit ? $this->hasilLabel($visit->status_kunjungan) : '-',
                $visit?->keterangan ?? '-',
                $visit?->visited_at?->format('d-m-Y H:i') ?? '-',
                $visit?->foto_bukti ?? '-',
            ];
        });
    }

    public function columnFormats(): array
    {
        return [
            'B' => '@',
        ];
    }

    public function styles(Worksheet $sheet)
    {
        $sheet->getStyle('A1:J1')->getFont()->setBold(true)->setColor(new \PhpOffice\PhpSpreadsheet\Style\Color('FFFFFF'));
        $sheet->getStyle('A1:J1')->getFill()
            ->setFillType(Fill::FILL_SOLID)
            ->getStartColor()->setARGB('1D4ED8');
        $sheet->getStyle('A1:J1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $sheet->getStyle('A1:J' . ($sheet->getHighestRow()))->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);

        $rowCount = $sheet->getHighestRow();
        if ($rowCount > 1) {
            $sheet->setAutoFilter('A1:J' . $rowCount);
            $sheet->getStyle('A2:J' . $rowCount)->getBorders()->getAllBorders()
                ->setBorderStyle(\PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN)
                ->getColor()->setARGB('D1D5DB');
        }

        return [];
    }

    private function statusLabel(string $status): string
    {
        return $status === 'sudah_dikunjungi' ? 'Sudah Dikunjungi' : 'Belum Dikunjungi';
    }

    private function hasilLabel(string $hasil): string
    {
        return match ($hasil) {
            'ada_orang' => 'Ada Orang',
            'rumah_kosong' => 'Rumah Kosong',
            'tidak_ada_orang' => 'Tidak Ada Orang',
            default => 'Lainnya',
        };
    }
}
