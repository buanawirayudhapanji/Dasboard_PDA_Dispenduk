<?php

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
use App\Models\User;
use App\Models\Village;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Route;
use Livewire\Livewire;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\XLSX\Writer;

function createDataEntryDimensions(): array
{
    $province = Province::query()->create([
        'kode_provinsi' => '35',
        'nama_provinsi' => 'JAWA TIMUR',
        'nama_normalisasi' => 'JAWA TIMUR',
    ]);
    $regency = Regency::query()->create([
        'provinsi_id' => $province->provinsi_id,
        'kode_kabupaten' => '3509',
        'nama_kabupaten' => 'JEMBER',
        'nama_normalisasi' => 'JEMBER',
        'jenis_kabupaten' => 'KABUPATEN',
    ]);
    $district = District::query()->create([
        'kabupaten_id' => $regency->kabupaten_id,
        'kode_kecamatan' => '3509010',
        'nama_kecamatan' => 'JOMBANG',
        'nama_normalisasi' => 'JOMBANG',
    ]);
    $village = Village::query()->create([
        'kecamatan_id' => $district->kecamatan_id,
        'kode_desa_kelurahan' => '3509010001',
        'nama_desa_kelurahan' => 'PADOMASAN',
        'nama_normalisasi' => 'PADOMASAN',
    ]);
    $period = Period::query()->create([
        'tahun' => 2025,
        'semester' => 2,
        'tanggal_mulai' => '2025-07-01',
        'tanggal_selesai' => '2025-12-31',
        'label_periode' => 'Semester II 2025',
    ]);
    $unit = Unit::query()->create(['kode_satuan' => 'ORANG', 'nama_satuan' => 'Orang']);
    $group = DataGroup::query()->create(['kode_kelompok' => 'PENDUDUK', 'nama_kelompok' => 'Penduduk', 'urutan_tampil' => 1]);
    $indicator = Indicator::query()->create([
        'kelompok_data_id' => $group->kelompok_data_id,
        'satuan_id' => $unit->satuan_id,
        'kode_indikator' => 'JUMLAH_PENDUDUK',
        'nama_indikator' => 'Jumlah Penduduk',
        'metode_agregasi' => 'SUM',
    ]);
    $category = Category::query()->create([
        'indikator_id' => $indicator->indikator_id,
        'kode_kategori' => 'SEMUA',
        'nama_kategori' => 'Semua',
        'urutan_tampil' => 1,
    ]);
    $gender = Gender::query()->create([
        'kode_jenis_kelamin' => 'L',
        'nama_jenis_kelamin' => 'Laki-laki',
        'urutan_tampil' => 1,
    ]);

    return compact('district', 'village', 'period', 'indicator', 'category', 'gender');
}

function createPopulationImportFile(array $rows): UploadedFile
{
    $temporaryPath = tempnam(sys_get_temp_dir(), 'pda-import-');
    $xlsxPath = $temporaryPath.'.xlsx';
    rename($temporaryPath, $xlsxPath);

    $writer = new Writer;
    $writer->openToFile($xlsxPath);
    $writer->addRow(Row::fromValues([
        'kode_desa_kelurahan',
        'kode_indikator',
        'kode_kategori',
        'kode_jenis_kelamin',
        'nilai',
    ]));

    foreach ($rows as $row) {
        $writer->addRow(Row::fromValues($row));
    }

    $writer->close();
    $contents = file_get_contents($xlsxPath);
    unlink($xlsxPath);

    return UploadedFile::fake()->createWithContent('data-penduduk.xlsx', $contents);
}

it('does not expose public staff registration', function () {
    expect(Route::has('register'))->toBeFalse();

    $this->get(route('login'))
        ->assertOk()
        ->assertSee('Akun petugas dikelola oleh administrator.')
        ->assertDontSee('Sign up');
});

it('redirects guests from the data entry page to login', function () {
    $this->get(route('data-entry'))->assertRedirect(route('login'));
    $this->get(route('data-edit'))->assertRedirect(route('login'));
});

it('shows edit data from its dedicated staff page', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('data-edit'))
        ->assertSee('Edit Data Kependudukan')
        ->assertSee('Edit periode');
});

it('requires every dimension and a non-negative value', function () {
    $user = User::factory()->create();

    Livewire::actingAs($user)
        ->test('pages::data-entry')
        ->set('value', '-1')
        ->call('save')
        ->assertHasErrors([
            'periodId' => 'required',
            'districtId' => 'required',
            'villageId' => 'required',
            'indicatorId' => 'required',
            'categoryId' => 'required',
            'genderId' => 'required',
            'value' => 'min',
        ]);
});

it('stores a valid population fact for authenticated staff', function () {
    $user = User::factory()->create();
    $province = Province::query()->create([
        'kode_provinsi' => '35',
        'nama_provinsi' => 'JAWA TIMUR',
        'nama_normalisasi' => 'JAWA TIMUR',
    ]);
    $regency = Regency::query()->create([
        'provinsi_id' => $province->provinsi_id,
        'kode_kabupaten' => '3509',
        'nama_kabupaten' => 'JEMBER',
        'nama_normalisasi' => 'JEMBER',
        'jenis_kabupaten' => 'KABUPATEN',
    ]);
    $district = District::query()->create([
        'kabupaten_id' => $regency->kabupaten_id,
        'kode_kecamatan' => '3509010',
        'nama_kecamatan' => 'JOMBANG',
        'nama_normalisasi' => 'JOMBANG',
    ]);
    $village = Village::query()->create([
        'kecamatan_id' => $district->kecamatan_id,
        'kode_desa_kelurahan' => '3509010001',
        'nama_desa_kelurahan' => 'PADOMASAN',
        'nama_normalisasi' => 'PADOMASAN',
    ]);
    $period = Period::query()->create([
        'tahun' => 2025,
        'semester' => 2,
        'tanggal_mulai' => '2025-07-01',
        'tanggal_selesai' => '2025-12-31',
        'label_periode' => 'Semester II 2025',
    ]);
    $unit = Unit::query()->create(['kode_satuan' => 'ORANG', 'nama_satuan' => 'Orang']);
    $group = DataGroup::query()->create(['kode_kelompok' => 'PENDUDUK', 'nama_kelompok' => 'Penduduk', 'urutan_tampil' => 1]);
    $indicator = Indicator::query()->create([
        'kelompok_data_id' => $group->kelompok_data_id,
        'satuan_id' => $unit->satuan_id,
        'kode_indikator' => 'JUMLAH_PENDUDUK',
        'nama_indikator' => 'Jumlah Penduduk',
        'metode_agregasi' => 'SUM',
    ]);
    $category = Category::query()->create([
        'indikator_id' => $indicator->indikator_id,
        'kode_kategori' => 'SEMUA',
        'nama_kategori' => 'Semua',
        'urutan_tampil' => 1,
    ]);
    $gender = Gender::query()->create([
        'kode_jenis_kelamin' => 'L',
        'nama_jenis_kelamin' => 'Laki-laki',
        'urutan_tampil' => 1,
    ]);

    Livewire::actingAs($user)
        ->test('pages::data-entry')
        ->set('periodId', $period->periode_id)
        ->set('districtId', $district->kecamatan_id)
        ->set('villageId', $village->desa_kelurahan_id)
        ->set('indicatorId', $indicator->indikator_id)
        ->set('categoryId', $category->kategori_id)
        ->set('genderId', $gender->jenis_kelamin_id)
        ->set('value', '5408')
        ->call('save')
        ->assertHasNoErrors()
        ->assertSee('Data berhasil disimpan ke database.');

    $this->assertDatabaseHas('fact_kependudukan', [
        'periode_id' => $period->periode_id,
        'desa_kelurahan_id' => $village->desa_kelurahan_id,
        'indikator_id' => $indicator->indikator_id,
        'kategori_id' => $category->kategori_id,
        'jenis_kelamin_id' => $gender->jenis_kelamin_id,
        'nilai' => 5408,
    ]);
});

it('allows staff to add a new reporting period', function () {
    $user = User::factory()->create();

    Livewire::actingAs($user)
        ->test('pages::data-entry')
        ->set('newPeriodYear', 2026)
        ->set('newPeriodSemester', '1')
        ->call('createPeriod')
        ->assertHasNoErrors()
        ->assertSee('Semester I 2026 berhasil ditambahkan');

    $this->assertDatabaseHas('dim_periode', [
        'tahun' => 2026,
        'semester' => 1,
        'label_periode' => 'Semester I 2026',
    ]);

    $period = Period::query()->where('tahun', 2026)->where('semester', 1)->firstOrFail();
    expect($period->tanggal_mulai->toDateString())->toBe('2026-01-01')
        ->and($period->tanggal_selesai->toDateString())->toBe('2026-06-30');
});

it('allows staff to save one stored value from the edit data table', function () {
    $user = User::factory()->create();
    $dimensions = createDataEntryDimensions();
    $source = DataSource::query()->create([
        'nama_dokumen' => 'Data Uji Kelola',
        'instansi' => 'Dispendukcapil Jember',
    ]);
    $fact = PopulationFact::query()->create([
        'periode_id' => $dimensions['period']->periode_id,
        'desa_kelurahan_id' => $dimensions['village']->desa_kelurahan_id,
        'indikator_id' => $dimensions['indicator']->indikator_id,
        'kategori_id' => $dimensions['category']->kategori_id,
        'jenis_kelamin_id' => $dimensions['gender']->jenis_kelamin_id,
        'sumber_data_id' => $source->sumber_data_id,
        'nilai' => 5408,
    ]);

    Livewire::actingAs($user)
        ->test('pages::data-entry')
        ->call('showManageData')
        ->set('managePerPage', 10)
        ->set("managedValues.{$fact->fakta_id}", '6000')
        ->call('saveManagedFact', $fact->fakta_id)
        ->assertHasNoErrors()
        ->assertSet('managePerPage', 10)
        ->assertSee('Nilai berhasil diperbarui.');

    $this->assertDatabaseHas('fact_kependudukan', [
        'fakta_id' => $fact->fakta_id,
        'nilai' => 6000,
    ]);
});

it('allows staff to edit a period label and date range without changing its identity', function () {
    $user = User::factory()->create();
    $dimensions = createDataEntryDimensions();

    Livewire::actingAs($user)
        ->test('pages::data-entry')
        ->call('editPeriod', $dimensions['period']->periode_id)
        ->set('editingPeriodLabel', 'Periode Validasi 2025')
        ->set('editingPeriodStartDate', '2025-07-02')
        ->set('editingPeriodEndDate', '2025-12-30')
        ->call('updatePeriod')
        ->assertHasNoErrors()
        ->assertSee('Periode berhasil diperbarui.');

    $this->assertDatabaseHas('dim_periode', [
        'periode_id' => $dimensions['period']->periode_id,
        'tahun' => 2025,
        'semester' => 2,
        'label_periode' => 'Periode Validasi 2025',
        'tanggal_mulai' => '2025-07-02',
        'tanggal_selesai' => '2025-12-30',
    ]);
});

it('previews and saves valid rows from an Excel file while skipping invalid rows', function () {
    $user = User::factory()->create();
    $dimensions = createDataEntryDimensions();
    $file = createPopulationImportFile([
        ['3509010001', 'JUMLAH_PENDUDUK', 'SEMUA', 'L', 5408],
        ['9999999999', 'JUMLAH_PENDUDUK', 'SEMUA', 'L', 120],
    ]);

    $component = Livewire::actingAs($user)
        ->test('pages::data-entry')
        ->set('inputMode', 'bulk')
        ->set('bulkPeriodId', $dimensions['period']->periode_id)
        ->set('importFile', $file)
        ->call('previewImport')
        ->assertHasNoErrors()
        ->assertSet('importTotal', 2)
        ->assertSet('importValid', 1)
        ->assertSet('importInvalid', 1)
        ->assertSee('kode desa/kelurahan 9999999999 tidak dikenal');

    $this->assertDatabaseMissing('fact_kependudukan', [
        'periode_id' => $dimensions['period']->periode_id,
        'nilai' => 5408,
    ]);

    $component
        ->call('saveImport')
        ->assertHasNoErrors()
        ->assertSee('1 baris valid berhasil disimpan ke database.');

    $this->assertDatabaseHas('fact_kependudukan', [
        'periode_id' => $dimensions['period']->periode_id,
        'desa_kelurahan_id' => $dimensions['village']->desa_kelurahan_id,
        'indikator_id' => $dimensions['indicator']->indikator_id,
        'kategori_id' => $dimensions['category']->kategori_id,
        'jenis_kelamin_id' => $dimensions['gender']->jenis_kelamin_id,
        'nilai' => 5408,
    ]);
});

it('downloads an Excel template for an indicator with historical data', function () {
    $user = User::factory()->create();
    $dimensions = createDataEntryDimensions();
    $source = DataSource::query()->create([
        'nama_dokumen' => 'Data Uji',
        'instansi' => 'Dispendukcapil Jember',
    ]);
    PopulationFact::query()->create([
        'periode_id' => $dimensions['period']->periode_id,
        'desa_kelurahan_id' => $dimensions['village']->desa_kelurahan_id,
        'indikator_id' => $dimensions['indicator']->indikator_id,
        'kategori_id' => $dimensions['category']->kategori_id,
        'jenis_kelamin_id' => $dimensions['gender']->jenis_kelamin_id,
        'sumber_data_id' => $source->sumber_data_id,
        'nilai' => 5408,
    ]);

    Livewire::actingAs($user)
        ->test('pages::data-entry')
        ->set('templateIndicatorId', $dimensions['indicator']->indikator_id)
        ->call('downloadTemplate')
        ->assertHasNoErrors()
        ->assertFileDownloaded('template-jumlah-penduduk.xlsx');
});
