<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\DataGroup;
use App\Models\DataSource;
use App\Models\District;
use App\Models\Gender;
use App\Models\Indicator;
use App\Models\Period;
use App\Models\PopulationFact;
use App\Models\Province;
use App\Models\Regency;
use App\Models\Unit;
use App\Models\Village;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use SplFileObject;

class PopulationDataSeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function (): void {
            $this->seedReferenceData();
            $this->seedPopulationFacts();
        });
    }

    private function seedReferenceData(): void
    {
        $province = Province::query()->firstOrCreate(
            ['kode_provinsi' => '35'],
            ['nama_provinsi' => 'JAWA TIMUR', 'nama_normalisasi' => 'JAWA TIMUR', 'aktif' => true],
        );

        $regency = Regency::query()->firstOrCreate(
            ['kode_kabupaten' => 'KAB_JEMBER'],
            [
                'provinsi_id' => $province->provinsi_id,
                'nama_kabupaten' => 'JEMBER',
                'nama_normalisasi' => 'JEMBER',
                'jenis_kabupaten' => 'KABUPATEN',
                'aktif' => true,
            ],
        );

        $rows = $this->csvRows(database_path('seeders/data/pda_wilayah.csv'));
        $districtIds = [];

        foreach ($rows as $row) {
            if ($row['tingkat_wilayah'] !== 'KECAMATAN') {
                continue;
            }

            $district = District::query()->firstOrCreate(
                ['kode_kecamatan' => $row['wilayah_id']],
                [
                    'kabupaten_id' => $regency->kabupaten_id,
                    'nama_kecamatan' => $row['nama_wilayah'],
                    'nama_normalisasi' => $row['nama_wilayah'],
                    'aktif' => true,
                ],
            );

            $districtIds[$row['wilayah_id']] = $district->kecamatan_id;
        }

        foreach ($rows as $row) {
            if ($row['tingkat_wilayah'] !== 'DESA/KELURAHAN') {
                continue;
            }

            Village::query()->firstOrCreate(
                ['kode_desa_kelurahan' => $row['wilayah_id']],
                [
                    'kecamatan_id' => $districtIds[$row['induk_wilayah_id']],
                    'nama_desa_kelurahan' => $row['nama_wilayah'],
                    'nama_normalisasi' => $row['nama_wilayah'],
                    'jenis_desa_kelurahan' => null,
                    'aktif' => true,
                ],
            );
        }

        Period::query()->firstOrCreate(
            ['tahun' => 2025, 'semester' => 2],
            [
                'tanggal_mulai' => '2025-07-01',
                'tanggal_selesai' => '2025-12-31',
                'label_periode' => 'Semester II 2025',
            ],
        );

        foreach ([
            ['L', 'Laki-laki', 1],
            ['P', 'Perempuan', 2],
            ['ALL', 'Semua', 3],
        ] as [$code, $name, $order]) {
            Gender::query()->firstOrCreate(
                ['kode_jenis_kelamin' => $code],
                ['nama_jenis_kelamin' => $name, 'urutan_tampil' => $order],
            );
        }

        $unit = Unit::query()->firstOrCreate(
            ['kode_satuan' => 'ORANG'],
            ['nama_satuan' => 'Orang', 'deskripsi' => 'Jumlah penduduk atau kepala keluarga'],
        );

        foreach ($this->groupDefinitions() as $order => $definition) {
            $group = DataGroup::query()->firstOrCreate(
                ['kode_kelompok' => $definition['code']],
                ['nama_kelompok' => $definition['name'], 'urutan_tampil' => $order + 1],
            );

            Indicator::query()->firstOrCreate(
                ['kelompok_data_id' => $group->kelompok_data_id, 'kode_indikator' => $definition['indicator']],
                [
                    'satuan_id' => $unit->satuan_id,
                    'nama_indikator' => $definition['indicator_name'],
                    'metode_agregasi' => 'SUM',
                    'aktif' => true,
                ],
            );
        }

        DataSource::query()->firstOrCreate(
            ['checksum_file' => 'e290df5b8adb156a4ad072325c12cd3c441e3cab17561fcc9d79248fa369eefa'],
            [
                'nama_dokumen' => '202502 PDA - FULL FIX.pdf',
                'instansi' => 'Dinas Kependudukan dan Pencatatan Sipil Kabupaten Jember',
                'versi' => 'Semester II 2025',
                'lokasi_file' => '202502 PDA - FULL FIX.pdf',
                'catatan' => 'Data dinormalisasi dari workbook DB 20252 PDA.xlsx.',
            ],
        );
    }

    private function seedPopulationFacts(): void
    {
        $factsPath = database_path('seeders/data/pda_facts.csv');
        $categories = [];

        foreach ($this->csvRows($factsPath) as $row) {
            $categories[$row['indikator_code']][$row['category_code']] = $row['category_name'];
        }

        $indicatorIds = Indicator::query()->pluck('indikator_id', 'kode_indikator');
        $categoryIds = [];

        foreach ($categories as $indicatorCode => $indicatorCategories) {
            foreach ($this->orderCategories($indicatorCode, $indicatorCategories) as $order => $categoryCode) {
                $category = Category::query()->firstOrCreate(
                    ['indikator_id' => $indicatorIds[$indicatorCode], 'kode_kategori' => $categoryCode],
                    ['nama_kategori' => $indicatorCategories[$categoryCode], 'urutan_tampil' => $order + 1, 'aktif' => true],
                );
                $categoryIds[$indicatorCode][$categoryCode] = $category->kategori_id;
            }
        }

        $periodIds = Period::query()->get()->keyBy(fn (Period $period): string => $period->tahun.'-'.$period->semester);
        $villageIds = Village::query()->pluck('desa_kelurahan_id', 'kode_desa_kelurahan');
        $genderIds = Gender::query()->pluck('jenis_kelamin_id', 'kode_jenis_kelamin');
        $sourceId = DataSource::query()
            ->where('checksum_file', 'e290df5b8adb156a4ad072325c12cd3c441e3cab17561fcc9d79248fa369eefa')
            ->valueOrFail('sumber_data_id');
        $now = now();
        $batch = [];

        foreach ($this->csvRows($factsPath) as $row) {
            $period = $periodIds[$row['tahun'].'-'.$row['semester']];
            $batch[] = [
                'periode_id' => $period->periode_id,
                'desa_kelurahan_id' => $villageIds[$row['wilayah_id']],
                'indikator_id' => $indicatorIds[$row['indikator_code']],
                'kategori_id' => $categoryIds[$row['indikator_code']][$row['category_code']],
                'jenis_kelamin_id' => $genderIds[$row['gender_code']],
                'sumber_data_id' => $sourceId,
                'nilai' => $row['nilai'],
                'created_at' => $now,
                'updated_at' => $now,
            ];

            if (count($batch) === 1000) {
                $this->upsertFacts($batch);
                $batch = [];
            }
        }

        if ($batch !== []) {
            $this->upsertFacts($batch);
        }
    }

    /** @param array<int, array<string, mixed>> $facts */
    private function upsertFacts(array $facts): void
    {
        PopulationFact::query()->upsert(
            $facts,
            ['periode_id', 'desa_kelurahan_id', 'indikator_id', 'kategori_id', 'jenis_kelamin_id'],
            ['sumber_data_id', 'nilai', 'updated_at'],
        );
    }

    /** @return array<int, array<string, string>> */
    private function csvRows(string $path): array
    {
        if (! is_file($path)) {
            throw new RuntimeException("Dataset tidak ditemukan: {$path}");
        }

        $file = new SplFileObject($path);
        $file->setFlags(SplFileObject::READ_CSV | SplFileObject::SKIP_EMPTY | SplFileObject::DROP_NEW_LINE);
        $file->setCsvControl(',', '"', '');
        $headers = $file->fgetcsv();

        if (! is_array($headers)) {
            throw new RuntimeException("CSV header is missing from [{$path}].");
        }

        $headers[0] = ltrim((string) $headers[0], "\xEF\xBB\xBF");
        $rows = [];

        while (! $file->eof()) {
            $values = $file->fgetcsv();

            if (! is_array($values) || count($values) !== count($headers)) {
                continue;
            }

            $rows[] = array_combine($headers, $values);
        }

        return $rows;
    }

    /** @return array<int, array{code: string, name: string, indicator: string, indicator_name: string}> */
    private function groupDefinitions(): array
    {
        return [
            ['code' => 'PENDUDUK', 'name' => 'Penduduk', 'indicator' => 'JUMLAH_PENDUDUK', 'indicator_name' => 'Jumlah Penduduk'],
            ['code' => 'KELUARGA', 'name' => 'Keluarga', 'indicator' => 'JUMLAH_KEPALA_KELUARGA', 'indicator_name' => 'Jumlah Kepala Keluarga'],
            ['code' => 'KELOMPOK_UMUR', 'name' => 'Kelompok Umur', 'indicator' => 'KELOMPOK_UMUR', 'indicator_name' => 'Penduduk Menurut Kelompok Umur'],
            ['code' => 'AGAMA', 'name' => 'Agama', 'indicator' => 'AGAMA', 'indicator_name' => 'Penduduk Menurut Agama'],
            ['code' => 'STATUS_PERKAWINAN', 'name' => 'Status Perkawinan', 'indicator' => 'STATUS_PERKAWINAN', 'indicator_name' => 'Penduduk Menurut Status Perkawinan'],
            ['code' => 'STATUS_HUBUNGAN', 'name' => 'Status Hubungan', 'indicator' => 'STATUS_HUBUNGAN', 'indicator_name' => 'Penduduk Menurut Status Hubungan Keluarga'],
            ['code' => 'PENDIDIKAN', 'name' => 'Pendidikan', 'indicator' => 'PENDIDIKAN', 'indicator_name' => 'Penduduk Menurut Pendidikan'],
            ['code' => 'PEKERJAAN', 'name' => 'Pekerjaan', 'indicator' => 'PEKERJAAN', 'indicator_name' => 'Penduduk Menurut Pekerjaan'],
        ];
    }

    /**
     * @param  array<string, string>  $categories
     * @return array<int, string>
     */
    private function orderCategories(string $indicatorCode, array $categories): array
    {
        if ($indicatorCode !== 'KELOMPOK_UMUR') {
            return array_keys($categories);
        }

        $order = ['0_4', '5_9', '10_14', '15_19', '20_24', '25_29', '30_34', '35_39', '40_44', '45_49', '50_54', '55_59', '60_64', '65_69', '70_74', '75'];

        return array_values(array_intersect($order, array_keys($categories)));
    }
}
