<?php

namespace App\Actions;

use App\Models\Indicator;
use App\Models\Period;
use App\Models\PopulationFact;
use App\Models\Village;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Common\Entity\Style\CellAlignment;
use OpenSpout\Common\Entity\Style\Style;
use OpenSpout\Writer\XLSX\Writer;

class CreatePopulationXlsxTemplate
{
    public function handle(Indicator $indicator): ?string
    {
        $sourcePeriodId = Period::query()
            ->whereHas('facts', fn ($query) => $query->where('indikator_id', $indicator->indikator_id))
            ->orderByDesc('tahun')
            ->orderByDesc('semester')
            ->value('periode_id');

        if ($sourcePeriodId === null) {
            return null;
        }

        $combinations = PopulationFact::query()
            ->where('periode_id', $sourcePeriodId)
            ->where('indikator_id', $indicator->indikator_id)
            ->with(['category', 'gender'])
            ->get()
            ->unique(fn (PopulationFact $fact): string => $fact->kategori_id.'|'.$fact->jenis_kelamin_id)
            ->values();
        $villages = Village::query()
            ->with('district')
            ->where('aktif', true)
            ->orderBy('kode_desa_kelurahan')
            ->get();

        $path = tempnam(sys_get_temp_dir(), 'pda-template-');
        if ($path === false) {
            throw new \RuntimeException('File template Excel tidak dapat dibuat.');
        }

        $xlsxPath = $path.'.xlsx';
        rename($path, $xlsxPath);

        $writer = new Writer;
        $writer->openToFile($xlsxPath);
        $sheet = $writer->getCurrentSheet();
        $sheet->setName('Input Data');
        $sheet->setColumnWidthForRange(16, 1, 1);
        $sheet->setColumnWidthForRange(24, 2, 3);
        $sheet->setColumnWidthForRange(18, 4, 4);
        $sheet->setColumnWidthForRange(30, 5, 5);
        $sheet->setColumnWidthForRange(18, 6, 6);
        $sheet->setColumnWidthForRange(24, 7, 7);
        $sheet->setColumnWidthForRange(14, 8, 8);
        $sheet->setColumnWidthForRange(18, 9, 10);
        $sheet->setColumnHiddenForRange(true, 1, 1);
        $sheet->setColumnHiddenForRange(true, 4, 4);
        $sheet->setColumnHiddenForRange(true, 6, 6);
        $sheet->setColumnHiddenForRange(true, 8, 8);

        $titleStyle = new Style(
            fontBold: true,
            fontSize: 14,
            fontColor: 'FFFFFF',
            backgroundColor: '1F4E78',
        );
        $instructionStyle = new Style(
            fontItalic: true,
            fontColor: '5B6573',
            shouldWrapText: true,
        );
        $headerStyle = new Style(
            fontBold: true,
            fontColor: 'FFFFFF',
            backgroundColor: '1F4E78',
            cellAlignment: CellAlignment::CENTER,
            shouldWrapText: true,
        );
        $bodyStyle = new Style(fontName: 'Arial');
        $inputStyle = new Style(
            fontName: 'Arial',
            backgroundColor: 'FFF2CC',
            format: '#,##0.####',
        );

        $writer->addRow(Row::fromValuesWithStyle([
            'Template Input Data Kependudukan',
            null,
            null,
            null,
            null,
            null,
            null,
            null,
            null,
            null,
        ], $titleStyle, 24));
        $writer->addRow(Row::fromValuesWithStyle([
            'Isi hanya kolom Nilai. Kolom kode disembunyikan untuk menjaga struktur impor.',
            null,
            null,
            null,
            null,
            null,
            null,
            null,
            null,
            null,
        ], $instructionStyle, 32));
        $writer->addRow(Row::fromValues(array_fill(0, 10, null)));
        $writer->addRow(Row::fromValuesWithStyle([
            'kode_desa_kelurahan',
            'Desa/Kelurahan',
            'Kecamatan',
            'kode_indikator',
            'Indikator',
            'kode_kategori',
            'Kategori',
            'kode_jenis_kelamin',
            'Jenis Kelamin',
            'Nilai',
        ], $headerStyle, 28));

        foreach ($villages as $village) {
            foreach ($combinations as $combination) {
                $writer->addRow(Row::fromValuesWithStyles([
                    $village->kode_desa_kelurahan,
                    $village->nama_desa_kelurahan,
                    $village->district->nama_kecamatan,
                    $indicator->kode_indikator,
                    $indicator->nama_indikator,
                    $combination->category->kode_kategori,
                    $combination->category->nama_kategori,
                    $combination->gender->kode_jenis_kelamin,
                    $combination->gender->nama_jenis_kelamin,
                    null,
                ], [
                    $bodyStyle,
                    $bodyStyle,
                    $bodyStyle,
                    $bodyStyle,
                    $bodyStyle,
                    $bodyStyle,
                    $bodyStyle,
                    $bodyStyle,
                    $bodyStyle,
                    $inputStyle,
                ]));
            }
        }

        $writer->close();

        return $xlsxPath;
    }
}
