<?php

use App\Models\District;
use App\Models\Period;
use App\Models\PopulationFact;
use Database\Seeders\PopulationDataSeeder;
use Livewire\Livewire;

it('aggregates the seeded village facts for Kabupaten Jember', function () {
    $this->seed(PopulationDataSeeder::class);

    Livewire::test('pages::dashboard')
        ->assertSee('2.638.087')
        ->assertSee('943.570');
});

it('updates totals when a district is selected', function () {
    $this->seed(PopulationDataSeeder::class);
    $district = District::query()->where('nama_kecamatan', 'JOMBANG')->firstOrFail();

    Livewire::test('pages::dashboard')
        ->set('districtId', $district->kecamatan_id)
        ->assertSee('56.425')
        ->assertSee('JOMBANG');
});

it('offers chart choices and shows historical totals by year', function () {
    $this->seed(PopulationDataSeeder::class);
    $latestFact = PopulationFact::query()
        ->whereHas('indicator.dataGroup', fn ($query) => $query->where('kode_kelompok', 'KELOMPOK_UMUR'))
        ->firstOrFail();
    $religionFact = PopulationFact::query()
        ->whereHas('indicator.dataGroup', fn ($query) => $query->where('kode_kelompok', 'AGAMA'))
        ->firstOrFail();
    $previousPeriod = Period::query()->create([
        'tahun' => 2024,
        'semester' => 2,
        'tanggal_mulai' => '2024-07-01',
        'tanggal_selesai' => '2024-12-31',
        'label_periode' => 'Semester II 2024',
    ]);
    PopulationFact::query()->create([
        'periode_id' => $previousPeriod->periode_id,
        'desa_kelurahan_id' => $latestFact->desa_kelurahan_id,
        'indikator_id' => $latestFact->indikator_id,
        'kategori_id' => $latestFact->kategori_id,
        'jenis_kelamin_id' => $latestFact->jenis_kelamin_id,
        'sumber_data_id' => $latestFact->sumber_data_id,
        'nilai' => 100,
    ]);
    PopulationFact::query()->create([
        'periode_id' => $previousPeriod->periode_id,
        'desa_kelurahan_id' => $religionFact->desa_kelurahan_id,
        'indikator_id' => $religionFact->indikator_id,
        'kategori_id' => $religionFact->kategori_id,
        'jenis_kelamin_id' => $religionFact->jenis_kelamin_id,
        'sumber_data_id' => $religionFact->sumber_data_id,
        'nilai' => 50,
    ]);

    Livewire::test('pages::dashboard')
        ->assertSee('Donat')
        ->assertSee('Batang')
        ->assertSee('Tren')
        ->set('chartType', 'trend')
        ->set('groupCode', 'AGAMA')
        ->assertSee('Perkembangan Agama pada Semester 2 tiap tahun.')
        ->assertSee('2024')
        ->assertSee('50');
});
