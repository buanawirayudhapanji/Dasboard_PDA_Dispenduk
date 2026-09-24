<?php

namespace App\Actions;

use App\Models\Category;
use App\Models\Gender;
use App\Models\Indicator;
use App\Models\Village;
use OpenSpout\Reader\XLSX\Reader;

class ParsePopulationXlsxImport
{
    private const REQUIRED_HEADERS = [
        'kode_desa_kelurahan',
        'kode_indikator',
        'kode_kategori',
        'kode_jenis_kelamin',
        'nilai',
    ];

    /**
     * @return array{
     *     rows: list<array<string, int|string>>,
     *     preview: list<array<string, int|string>>,
     *     errors: list<array{line: int, message: string}>,
     *     total: int,
     *     invalid: int
     * }
     */
    public function handle(string $path, int $periodId, int $sourceId): array
    {
        $villages = Village::query()
            ->with('district:kecamatan_id,nama_kecamatan')
            ->get(['desa_kelurahan_id', 'kecamatan_id', 'kode_desa_kelurahan', 'nama_desa_kelurahan'])
            ->keyBy('kode_desa_kelurahan');
        $indicators = Indicator::query()
            ->get(['indikator_id', 'kode_indikator', 'nama_indikator'])
            ->keyBy('kode_indikator');
        $categories = Category::query()
            ->get(['kategori_id', 'indikator_id', 'kode_kategori', 'nama_kategori'])
            ->keyBy(fn (Category $category): string => $category->indikator_id.'|'.$category->kode_kategori);
        $genders = Gender::query()
            ->get(['jenis_kelamin_id', 'kode_jenis_kelamin', 'nama_jenis_kelamin'])
            ->keyBy('kode_jenis_kelamin');
        $rows = [];
        $preview = [];
        $errors = [];
        $seenKeys = [];
        $total = 0;
        $invalid = 0;
        $headers = [];
        $reader = new Reader;

        try {
            $reader->open($path);
            foreach ($reader->getSheetIterator() as $sheet) {
                $line = 0;

                foreach ($sheet->getRowIterator() as $row) {
                    $line++;
                    $columns = array_map(
                        fn ($value): string => is_scalar($value) || $value === null ? trim((string) $value) : '',
                        $row->toArray(),
                    );

                    if ($headers === []) {
                        $candidateHeaders = $this->normalizeHeaders($columns);
                        if (count(array_intersect(self::REQUIRED_HEADERS, $candidateHeaders)) === count(self::REQUIRED_HEADERS)) {
                            $headers = $candidateHeaders;
                        }

                        continue;
                    }

                    if ($this->isEmptyRow($columns)) {
                        continue;
                    }

                    $total++;
                    if ($total > 10000) {
                        $invalid++;
                        $errors[] = ['line' => $line, 'message' => 'Maksimal 10.000 baris per unggahan.'];

                        break 2;
                    }

                    $record = $this->combineRow($headers, $columns);
                    $villageCode = trim((string) ($record['kode_desa_kelurahan'] ?? ''));
                    $indicatorCode = trim((string) ($record['kode_indikator'] ?? ''));
                    $categoryCode = trim((string) ($record['kode_kategori'] ?? ''));
                    $genderCode = trim((string) ($record['kode_jenis_kelamin'] ?? ''));
                    $numericValue = $this->normalizeNumber((string) ($record['nilai'] ?? ''));
                    $village = $villages->get($villageCode);
                    $indicator = $indicators->get($indicatorCode);
                    $category = $indicator === null ? null : $categories->get($indicator->indikator_id.'|'.$categoryCode);
                    $gender = $genders->get($genderCode);
                    $rowErrors = [];

                    if ($village === null) {
                        $rowErrors[] = "kode desa/kelurahan {$villageCode} tidak dikenal";
                    }
                    if ($indicator === null) {
                        $rowErrors[] = "kode indikator {$indicatorCode} tidak dikenal";
                    }
                    if ($category === null) {
                        $rowErrors[] = "kode kategori {$categoryCode} tidak sesuai dengan indikator";
                    }
                    if ($gender === null) {
                        $rowErrors[] = "kode jenis kelamin {$genderCode} tidak dikenal";
                    }
                    if ($numericValue === null || (float) $numericValue < 0 || (float) $numericValue > 9999999999999999) {
                        $rowErrors[] = 'nilai harus berupa angka antara 0 dan 9.999.999.999.999.999';
                    }

                    $uniqueKey = implode('|', [$villageCode, $indicatorCode, $categoryCode, $genderCode]);
                    if (isset($seenKeys[$uniqueKey])) {
                        $rowErrors[] = 'kombinasi dimensi duplikat dengan baris '.$seenKeys[$uniqueKey];
                    }

                    if ($rowErrors !== []) {
                        $invalid++;
                        if (count($errors) < 100) {
                            $errors[] = ['line' => $line, 'message' => implode('; ', $rowErrors).'.'];
                        }

                        continue;
                    }

                    $seenKeys[$uniqueKey] = $line;
                    $rows[] = [
                        'periode_id' => $periodId,
                        'desa_kelurahan_id' => $village->desa_kelurahan_id,
                        'indikator_id' => $indicator->indikator_id,
                        'kategori_id' => $category->kategori_id,
                        'jenis_kelamin_id' => $gender->jenis_kelamin_id,
                        'sumber_data_id' => $sourceId,
                        'nilai' => $numericValue,
                    ];

                    if (count($preview) < 10) {
                        $preview[] = [
                            'line' => $line,
                            'village' => $village->nama_desa_kelurahan,
                            'district' => $village->district->nama_kecamatan,
                            'indicator' => $indicator->nama_indikator,
                            'category' => $category->nama_kategori,
                            'gender' => $gender->kode_jenis_kelamin,
                            'value' => $numericValue,
                        ];
                    }
                }

                break;
            }
        } finally {
            $reader->close();
        }

        if ($headers === []) {
            return [
                'rows' => [],
                'preview' => [],
                'errors' => [['line' => 1, 'message' => 'Header template Excel tidak ditemukan. Gunakan template yang diunduh dari aplikasi.']],
                'total' => 0,
                'invalid' => 1,
            ];
        }

        return compact('rows', 'preview', 'errors', 'total', 'invalid');
    }

    /**
     * @param  array<int, string>  $headers
     * @return array<int, string>
     */
    private function normalizeHeaders(array $headers): array
    {
        return array_values(array_map(
            fn (string $header): string => mb_strtolower(trim($header, "\xEF\xBB\xBF \t\n\r\0\x0B")),
            $headers,
        ));
    }

    /** @param array<int, string> $columns */
    private function isEmptyRow(array $columns): bool
    {
        return count(array_filter($columns, fn (string $value): bool => $value !== '')) === 0;
    }

    /**
     * @param  array<int, string>  $headers
     * @param  array<int, string>  $columns
     * @return array<string, string|null>
     */
    private function combineRow(array $headers, array $columns): array
    {
        $columns = array_pad(array_slice($columns, 0, count($headers)), count($headers), null);

        return array_combine($headers, $columns) ?: [];
    }

    private function normalizeNumber(string $value): ?string
    {
        return is_numeric($value) ? $value : null;
    }
}
