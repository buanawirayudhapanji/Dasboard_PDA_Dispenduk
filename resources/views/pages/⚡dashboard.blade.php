<?php

use App\Models\DataGroup;
use App\Models\District;
use App\Models\Period;
use App\Models\PopulationFact;
use App\Models\Village;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

new #[Layout('components.layouts.public')] #[Title('Dashboard Kependudukan Jember')] class extends Component
{
    #[Url]
    public ?int $year = null;

    #[Url]
    public ?int $semester = null;

    #[Url]
    public ?int $districtId = null;

    #[Url]
    public ?int $villageId = null;

    #[Url]
    public string $groupCode = 'KELOMPOK_UMUR';

    #[Url]
    public string $chartType = 'pie';

    public function mount(): void
    {
        $latestPeriod = Period::query()
            ->orderByDesc('tahun')
            ->orderByDesc('semester')
            ->first();

        $this->year ??= $latestPeriod?->tahun;
        $this->semester ??= $latestPeriod?->semester;
    }

    /** Mengembalikan semua filter ke kondisi awal (periode terbaru, seluruh Jember). */
    public function resetFilters(): void
    {
        $this->districtId = null;
        $this->villageId = null;
        $this->year = null;
        $this->semester = null;
        $this->groupCode = 'KELOMPOK_UMUR';
        $this->chartType = 'pie';
        $this->mount();

        unset($this->villages, $this->period, $this->chartRows, $this->yearlyChartRows, $this->summary);
    }

    public function updatedDistrictId(): void
    {
        $this->villageId = null;
        unset($this->villages, $this->chartRows, $this->yearlyChartRows, $this->summary);
    }

    public function updatedYear(): void
    {
        unset($this->period, $this->chartRows, $this->yearlyChartRows, $this->summary);
    }

    public function updatedSemester(): void
    {
        unset($this->period, $this->chartRows, $this->yearlyChartRows, $this->summary);
    }

    public function updatedVillageId(): void
    {
        unset($this->chartRows, $this->yearlyChartRows, $this->summary);
    }

    public function updatedGroupCode(): void
    {
        unset($this->chartRows, $this->yearlyChartRows);
    }

    public function updatedChartType(): void
    {
        if (! in_array($this->chartType, ['pie', 'bar', 'trend'], true)) {
            $this->chartType = 'pie';
        }
    }

    #[Computed]
    public function years(): Collection
    {
        return Period::query()->select('tahun')->distinct()->orderByDesc('tahun')->pluck('tahun');
    }

    #[Computed]
    public function districts(): Collection
    {
        return District::query()->where('aktif', true)->orderBy('nama_kecamatan')->get();
    }

    #[Computed]
    public function villageCount(): int
    {
        return Village::query()->count();
    }

    #[Computed]
    public function villages(): Collection
    {
        return Village::query()
            ->when($this->districtId, fn (Builder $query) => $query->where('kecamatan_id', $this->districtId))
            ->where('aktif', true)
            ->orderBy('nama_desa_kelurahan')
            ->get();
    }

    #[Computed]
    public function groups(): Collection
    {
        return DataGroup::query()->orderBy('urutan_tampil')->get();
    }

    #[Computed]
    public function period(): ?Period
    {
        return Period::query()
            ->where('tahun', $this->year)
            ->where('semester', $this->semester)
            ->first();
    }

    #[Computed]
    public function summary(): array
    {
        if (! $this->period) {
            return ['population' => 0, 'male' => 0, 'female' => 0, 'families' => 0, 'average' => 0.0];
        }

        $population = $this->metric('JUMLAH_PENDUDUK');
        $male = $this->metric('JUMLAH_PENDUDUK', 'L');
        $female = $this->metric('JUMLAH_PENDUDUK', 'P');
        $families = $this->metric('JUMLAH_KEPALA_KELUARGA');

        return [
            'population' => $population,
            'male' => $male,
            'female' => $female,
            'families' => $families,
            'average' => $families > 0 ? $population / $families : 0.0,
        ];
    }

    #[Computed]
    public function chartRows(): Collection
    {
        if (! $this->period) {
            return collect();
        }

        $rows = $this->scopedFacts()
            ->join('dim_indikator', 'fact_kependudukan.indikator_id', '=', 'dim_indikator.indikator_id')
            ->join('dim_kelompok_data', 'dim_indikator.kelompok_data_id', '=', 'dim_kelompok_data.kelompok_data_id')
            ->join('dim_kategori', 'fact_kependudukan.kategori_id', '=', 'dim_kategori.kategori_id')
            ->join('dim_jenis_kelamin', 'fact_kependudukan.jenis_kelamin_id', '=', 'dim_jenis_kelamin.jenis_kelamin_id')
            ->where('dim_kelompok_data.kode_kelompok', $this->groupCode)
            ->selectRaw('dim_kategori.kategori_id, dim_kategori.nama_kategori, dim_kategori.urutan_tampil, dim_jenis_kelamin.kode_jenis_kelamin, SUM(fact_kependudukan.nilai) as total')
            ->groupBy('dim_kategori.kategori_id', 'dim_kategori.nama_kategori', 'dim_kategori.urutan_tampil', 'dim_jenis_kelamin.kode_jenis_kelamin')
            ->orderBy('dim_kategori.urutan_tampil')
            ->get();

        return $rows->groupBy('kategori_id')->map(function (Collection $categoryRows): array {
            $first = $categoryRows->first();
            $byGender = $categoryRows->pluck('total', 'kode_jenis_kelamin');
            $male = (float) ($byGender['L'] ?? 0);
            $female = (float) ($byGender['P'] ?? 0);
            $all = (float) ($byGender['ALL'] ?? 0);

            return [
                'name' => $first->nama_kategori,
                'male' => $male,
                'female' => $female,
                'total' => $all > 0 ? $all : $male + $female,
            ];
        })->values();
    }

    #[Computed]
    public function yearlyChartRows(): Collection
    {
        $rows = $this->scopedFactsAcrossPeriods()
            ->join('dim_periode', 'fact_kependudukan.periode_id', '=', 'dim_periode.periode_id')
            ->join('dim_indikator', 'fact_kependudukan.indikator_id', '=', 'dim_indikator.indikator_id')
            ->join('dim_kelompok_data', 'dim_indikator.kelompok_data_id', '=', 'dim_kelompok_data.kelompok_data_id')
            ->join('dim_kategori', 'fact_kependudukan.kategori_id', '=', 'dim_kategori.kategori_id')
            ->join('dim_jenis_kelamin', 'fact_kependudukan.jenis_kelamin_id', '=', 'dim_jenis_kelamin.jenis_kelamin_id')
            ->where('dim_kelompok_data.kode_kelompok', $this->groupCode)
            ->where('dim_periode.semester', $this->semester)
            ->selectRaw('dim_periode.tahun, dim_kategori.kategori_id, dim_jenis_kelamin.kode_jenis_kelamin, SUM(fact_kependudukan.nilai) as total')
            ->groupBy('dim_periode.tahun', 'dim_kategori.kategori_id', 'dim_jenis_kelamin.kode_jenis_kelamin')
            ->orderBy('dim_periode.tahun')
            ->get();

        return $rows->groupBy('tahun')->map(function (Collection $yearRows, int|string $year): array {
            $total = $yearRows->groupBy('kategori_id')->sum(function (Collection $categoryRows): float {
                $byGender = $categoryRows->pluck('total', 'kode_jenis_kelamin');
                $all = (float) ($byGender['ALL'] ?? 0);

                return $all > 0 ? $all : (float) ($byGender['L'] ?? 0) + (float) ($byGender['P'] ?? 0);
            });

            return ['year' => (int) $year, 'total' => $total];
        })->values();
    }

    #[Computed]
    public function locationLabel(): string
    {
        if ($this->villageId) {
            $village = Village::query()->with('district')->find($this->villageId);

            return $village ? $village->nama_desa_kelurahan.', '.$village->district->nama_kecamatan : 'Kabupaten Jember';
        }

        if ($this->districtId) {
            return District::query()->whereKey($this->districtId)->value('nama_kecamatan') ?? 'Kabupaten Jember';
        }

        return 'Kabupaten Jember';
    }

    private function metric(string $indicatorCode, ?string $genderCode = null): int
    {
        return (int) $this->scopedFacts()
            ->join('dim_indikator', 'fact_kependudukan.indikator_id', '=', 'dim_indikator.indikator_id')
            ->join('dim_jenis_kelamin', 'fact_kependudukan.jenis_kelamin_id', '=', 'dim_jenis_kelamin.jenis_kelamin_id')
            ->where('dim_indikator.kode_indikator', $indicatorCode)
            ->when($genderCode, fn (Builder $query) => $query->where('dim_jenis_kelamin.kode_jenis_kelamin', $genderCode))
            ->sum('fact_kependudukan.nilai');
    }

    private function scopedFacts(): Builder
    {
        return $this->scopedFactsAcrossPeriods()
            ->where('fact_kependudukan.periode_id', $this->period?->periode_id ?? 0);
    }

    /** @return Builder<PopulationFact> */
    private function scopedFactsAcrossPeriods(): Builder
    {
        return PopulationFact::query()
            ->when($this->villageId, fn (Builder $query) => $query->where('fact_kependudukan.desa_kelurahan_id', $this->villageId))
            ->when($this->districtId && ! $this->villageId, function (Builder $query): void {
                $query->whereIn('fact_kependudukan.desa_kelurahan_id', Village::query()
                    ->select('desa_kelurahan_id')
                    ->where('kecamatan_id', $this->districtId));
            });
    }
};
?>

@php
    $field = 'w-full rounded-xl border border-pink-200 bg-pink-50/50 px-3 py-2.5 text-sm text-rose-950 shadow-sm transition focus:border-pink-500 focus:bg-white focus:outline-none focus-visible:ring-2 focus-visible:ring-pink-400/30';
    $card = 'rounded-2xl border border-pink-100 bg-white shadow-sm shadow-pink-100/50';
    $groupName = $this->groups->firstWhere('kode_kelompok', $groupCode)?->nama_kelompok ?? 'Kelompok data';
    $nf = fn ($n, $d = 0) => number_format($n, $d, ',', '.');
    $filtersChanged = $districtId || $villageId || $groupCode !== 'KELOMPOK_UMUR' || $chartType !== 'pie';
@endphp

<div class="min-h-screen bg-[#fff6fa] pb-16 text-rose-950">
    {{-- Header --}}
    <header class="relative overflow-hidden bg-gradient-to-br from-pink-500 via-rose-400 to-pink-600 text-white">
        <div class="pointer-events-none absolute -left-16 -top-20 size-72 rounded-full bg-white/10"></div>
        <div class="pointer-events-none absolute -bottom-32 right-10 size-96 rounded-full border-[28px] border-white/10"></div>
        <div class="relative mx-auto flex max-w-7xl flex-col gap-8 px-4 py-12 sm:px-6 lg:flex-row lg:items-end lg:justify-between lg:px-8 lg:py-16">
            <div class="max-w-2xl">
                <p class="text-sm font-medium text-pink-100">Data agregat kependudukan · Kabupaten Jember</p>
                <h1 class="mt-3 text-3xl font-semibold tracking-tight sm:text-5xl">Kependudukan Jember</h1>
                <p class="mt-3 text-base leading-7 text-white/90">
                    Lihat jumlah dan komposisi penduduk per kecamatan hingga desa atau kelurahan. Ubah filter di bawah, grafik dan tabel ikut menyesuaikan.
                </p>
            </div>

            <dl class="flex gap-3 text-sm">
                <div class="min-w-28 rounded-2xl bg-white/90 px-4 py-3 text-pink-950 shadow-lg shadow-pink-900/10 ring-1 ring-white/50">
                    <dt class="text-pink-500">Periode</dt>
                    <dd class="mt-1 text-base font-semibold">{{ $this->period?->label_periode ?? '—' }}</dd>
                </div>
                <div class="min-w-28 rounded-2xl bg-white/90 px-4 py-3 text-pink-950 shadow-lg shadow-pink-900/10 ring-1 ring-white/50">
                    <dt class="text-pink-500">Kecamatan</dt>
                    <dd class="mt-1 text-base font-semibold tabular-nums">{{ $nf($this->districts->count()) }}</dd>
                </div>
                <div class="min-w-28 rounded-2xl bg-white/90 px-4 py-3 text-pink-950 shadow-lg shadow-pink-900/10 ring-1 ring-white/50">
                    <dt class="text-pink-500">Desa/Kelurahan</dt>
                    <dd class="mt-1 text-base font-semibold tabular-nums">{{ $nf($this->villageCount) }}</dd>
                </div>
            </dl>
        </div>
    </header>

    <main class="mx-auto grid max-w-7xl gap-6 px-4 py-8 sm:px-6 lg:px-8">
        {{-- Filter --}}
        <section aria-labelledby="filter-title" class="{{ $card }} p-5 sm:p-6">
            <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
                <h2 id="filter-title" class="flex items-center gap-2 text-base font-semibold"><span class="grid size-7 place-items-center rounded-lg bg-pink-100 text-pink-600">⌘</span>Filter data</h2>
                <div class="flex items-center gap-3">
                    <span wire:loading.delay wire:target="year,semester,districtId,villageId,groupCode,chartType,resetFilters" class="flex items-center gap-2 text-sm text-pink-500">
                        <svg class="size-4 animate-spin" viewBox="0 0 24 24" fill="none" aria-hidden="true"><circle cx="12" cy="12" r="9" stroke="currentColor" stroke-opacity=".25" stroke-width="3"/><path d="M21 12a9 9 0 0 0-9-9" stroke="currentColor" stroke-width="3" stroke-linecap="round"/></svg>
                        Memuat data…
                    </span>
                    @if ($filtersChanged)
                        <button type="button" wire:click="resetFilters" class="rounded-lg px-3 py-1.5 text-sm font-medium text-pink-600 transition hover:bg-pink-50 focus-visible:ring-2 focus-visible:ring-pink-500/40">Atur ulang filter</button>
                    @endif
                </div>
            </div>

            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-12">
                <label class="grid gap-1.5 text-sm font-medium lg:col-span-2">
                    Tahun
                    <select wire:model.live="year" class="{{ $field }}">
                        @foreach ($this->years as $availableYear)
                            <option value="{{ $availableYear }}">{{ $availableYear }}</option>
                        @endforeach
                    </select>
                </label>

                <label class="grid gap-1.5 text-sm font-medium lg:col-span-2">
                    Semester
                    <select wire:model.live="semester" class="{{ $field }}">
                        <option value="1">Semester I</option>
                        <option value="2">Semester II</option>
                    </select>
                </label>

                <label class="grid gap-1.5 text-sm font-medium lg:col-span-4">
                    Kecamatan
                    <select wire:model.live="districtId" class="{{ $field }}">
                        <option value="">Semua kecamatan</option>
                        @foreach ($this->districts as $district)
                            <option value="{{ $district->kecamatan_id }}">{{ $district->nama_kecamatan }}</option>
                        @endforeach
                    </select>
                </label>

                <label class="grid gap-1.5 text-sm font-medium sm:col-span-2 lg:col-span-4">
                    Desa/Kelurahan
                    <select wire:model.live="villageId" wire:key="villages-{{ $districtId ?? 'all' }}" class="{{ $field }}">
                        <option value="">Semua desa/kelurahan</option>
                        @foreach ($this->villages as $village)
                            <option value="{{ $village->desa_kelurahan_id }}">{{ $village->nama_desa_kelurahan }}</option>
                        @endforeach
                    </select>
                </label>

                <label class="grid gap-1.5 text-sm font-medium sm:col-span-2 lg:col-span-12">
                    Kelompok data
                    <select wire:model.live="groupCode" class="{{ $field }} lg:max-w-md">
                        @foreach ($this->groups as $group)
                            <option value="{{ $group->kode_kelompok }}">{{ $group->nama_kelompok }}</option>
                        @endforeach
                    </select>
                </label>
            </div>
        </section>

        @if (! $this->period)
            <flux:callout icon="information-circle" color="amber">
                <flux:callout.heading>Data untuk periode ini belum tersedia</flux:callout.heading>
                <flux:callout.text>Pilih kombinasi tahun dan semester lain, atau atur ulang filter untuk kembali ke periode terbaru.</flux:callout.text>
            </flux:callout>
        @else
            @php
                $s = $this->summary;
                $malePct = $s['population'] > 0 ? $s['male'] / $s['population'] * 100 : 0;
                $femalePct = $s['population'] > 0 ? $s['female'] / $s['population'] * 100 : 0;
                $grandTotal = max(1, (float) $this->chartRows->sum('total'));
            @endphp

            <div wire:loading.class="opacity-60" wire:target="year,semester,districtId,villageId,groupCode" class="grid gap-6 transition-opacity">
                {{-- Ringkasan --}}
                <section aria-label="Ringkasan {{ $this->locationLabel }}" class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                    <article class="rounded-2xl bg-gradient-to-br from-pink-500 to-rose-400 p-5 text-white shadow-lg shadow-pink-200/60 sm:col-span-2 xl:col-span-1">
                        <p class="text-sm text-pink-100">Jumlah penduduk</p>
                        <p class="mt-2 text-3xl font-semibold tabular-nums tracking-tight">{{ $nf($s['population']) }}</p>
                        <div class="mt-4 flex h-2 overflow-hidden rounded-full bg-white/30" role="img" aria-label="Laki-laki {{ $nf($malePct, 1) }}%, perempuan {{ $nf($femalePct, 1) }}%">
                            <div class="bg-sky-200" style="width: {{ $malePct }}%"></div>
                            <div class="bg-white" style="width: {{ $femalePct }}%"></div>
                        </div>
                        <p class="mt-2 text-xs text-pink-100">{{ $this->locationLabel }}</p>
                    </article>

                    <article class="{{ $card }} p-5">
                        <p class="flex items-center gap-2 text-sm text-pink-600"><span class="size-2.5 rounded-full bg-sky-400"></span>Laki-laki</p>
                        <p class="mt-2 text-2xl font-semibold tabular-nums tracking-tight">{{ $nf($s['male']) }}</p>
                        <p class="mt-1 text-sm text-pink-500">{{ $nf($malePct, 1) }}% dari total</p>
                    </article>

                    <article class="{{ $card }} p-5">
                        <p class="flex items-center gap-2 text-sm text-pink-600"><span class="size-2.5 rounded-full bg-pink-500"></span>Perempuan</p>
                        <p class="mt-2 text-2xl font-semibold tabular-nums tracking-tight">{{ $nf($s['female']) }}</p>
                        <p class="mt-1 text-sm text-pink-500">{{ $nf($femalePct, 1) }}% dari total</p>
                    </article>

                    <article class="{{ $card }} p-5">
                        <p class="flex items-center gap-2 text-sm text-pink-600"><span class="size-2.5 rounded-full bg-amber-400"></span>Kepala keluarga</p>
                        <p class="mt-2 text-2xl font-semibold tabular-nums tracking-tight">{{ $nf($s['families']) }}</p>
                        <p class="mt-1 text-sm text-pink-500">Rata-rata {{ $nf($s['average'], 2) }} orang per keluarga</p>
                    </article>
                </section>

                {{-- Grafik + tabel --}}
                <section class="grid items-start gap-6 xl:grid-cols-[1.1fr_0.9fr]">
                    <article class="{{ $card }} p-5 sm:p-6">
                        <div class="flex flex-wrap items-start justify-between gap-4">
                            <div class="min-w-0">
                                <p class="text-sm text-pink-500">{{ $this->locationLabel }} · {{ $this->period->label_periode }}</p>
                                <h2 class="mt-1 text-xl font-semibold">{{ $groupName }}</h2>
                            </div>

                            <div class="inline-flex rounded-xl bg-pink-50 p-1" role="group" aria-label="Jenis grafik">
                                @foreach (['pie' => 'Donat', 'bar' => 'Batang', 'trend' => 'Tren'] as $key => $label)
                                    <button type="button" wire:click="$set('chartType', '{{ $key }}')" aria-pressed="{{ $chartType === $key ? 'true' : 'false' }}"
                                        class="rounded-lg px-3.5 py-1.5 text-sm font-medium transition focus-visible:ring-2 focus-visible:ring-pink-500/40 {{ $chartType === $key ? 'bg-pink-500 text-white shadow-sm' : 'text-pink-600 hover:bg-pink-100' }}">
                                        {{ $label }}
                                    </button>
                                @endforeach
                            </div>
                        </div>

                        @php
                            $palette = ['#f472b6', '#fb7185', '#fdba74', '#fcd34d', '#86efac', '#6ee7b7', '#67e8f9', '#93c5fd', '#c4b5fd', '#f0abfc', '#fda4af', '#fca5a5'];
                            $emptyChart = 'mt-7 rounded-xl border border-dashed border-pink-200 bg-pink-50/50 p-8 text-center text-sm text-pink-500';
                        @endphp

                        @if ($chartType === 'pie')
                            @if ($this->chartRows->isNotEmpty())
                                @php
                                    $offset = 0.0;
                                    $segments = [];
                                    foreach ($this->chartRows as $index => $row) {
                                        $pct = ((float) $row['total'] / $grandTotal) * 100;
                                        $segments[] = $palette[$index % count($palette)]." {$offset}% ".($offset + $pct).'%';
                                        $offset += $pct;
                                    }
                                @endphp
                                <div class="mt-7 grid items-center gap-8 md:grid-cols-[15rem_1fr]">
                                    <div class="relative mx-auto aspect-square w-full max-w-60 rounded-full" style="background: conic-gradient({{ implode(', ', $segments) }});" role="img" aria-label="Diagram donat {{ $groupName }}">
                                        <div class="absolute inset-[22%] grid place-content-center rounded-full bg-white text-center">
                                            <span class="text-xs text-pink-500">Total</span>
                                            <span class="text-lg font-semibold tabular-nums">{{ $nf($grandTotal) }}</span>
                                        </div>
                                    </div>
                                    <ul class="grid max-h-80 gap-1 overflow-auto pr-1">
                                        @foreach ($this->chartRows as $index => $row)
                                            <li wire:key="pie-chart-row-{{ $index }}" class="flex items-center justify-between gap-4 rounded-lg px-2 py-1.5 text-sm hover:bg-pink-50">
                                                <span class="flex min-w-0 items-center gap-2.5">
                                                    <span class="size-3 shrink-0 rounded-sm" style="background-color: {{ $palette[$index % count($palette)] }};"></span>
                                                    <span class="truncate">{{ $row['name'] }}</span>
                                                </span>
                                                <span class="flex shrink-0 items-baseline gap-3 tabular-nums">
                                                    <span class="text-pink-500">{{ $nf($row['total']) }}</span>
                                                    <span class="w-14 text-right font-medium">{{ $nf(($row['total'] / $grandTotal) * 100, 1) }}%</span>
                                                </span>
                                            </li>
                                        @endforeach
                                    </ul>
                                </div>
                            @else
                                <p class="{{ $emptyChart }}">Belum ada data untuk pilihan ini. Coba ganti kelompok data atau wilayah.</p>
                            @endif
                        @elseif ($chartType === 'bar')
                            @php $maxValue = max(1, (float) $this->chartRows->max('total')); @endphp
                            @if ($this->chartRows->isNotEmpty())
                                <div class="mt-5 flex items-center gap-4 text-xs text-pink-500">
                                    <span class="flex items-center gap-1.5"><span class="size-2.5 rounded-sm bg-sky-400"></span>Laki-laki</span>
                                    <span class="flex items-center gap-1.5"><span class="size-2.5 rounded-sm bg-pink-500"></span>Perempuan</span>
                                </div>
                                <div class="mt-4 grid gap-4">
                                    @foreach ($this->chartRows as $index => $row)
                                        @php
                                            $gSum = $row['male'] + $row['female'];
                                            $barWidth = max(1.5, ($row['total'] / $maxValue) * 100);
                                        @endphp
                                        <div wire:key="bar-chart-row-{{ $index }}" class="grid gap-1.5">
                                            <div class="flex items-center justify-between gap-4 text-sm">
                                                <span class="truncate font-medium">{{ $row['name'] }}</span>
                                                <span class="tabular-nums text-pink-500">{{ $nf($row['total']) }}</span>
                                            </div>
                                            <div class="h-3 rounded-full bg-pink-100">
                                                <div class="flex h-full overflow-hidden rounded-full transition-all duration-500" style="width: {{ $barWidth }}%">
                                                    @if ($gSum > 0)
                                                        <div class="bg-sky-400" style="width: {{ $row['male'] / $gSum * 100 }}%"></div>
                                                        <div class="bg-pink-500" style="width: {{ $row['female'] / $gSum * 100 }}%"></div>
                                                    @else
                                                        <div class="w-full bg-pink-500"></div>
                                                    @endif
                                                </div>
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                            @else
                                <p class="{{ $emptyChart }}">Belum ada data untuk pilihan ini. Coba ganti kelompok data atau wilayah.</p>
                            @endif
                        @else
                            @php
                                $trendRows = $this->yearlyChartRows->values();
                                $n = $trendRows->count();
                                $min = (float) ($trendRows->min('total') ?? 0);
                                $max = (float) ($trendRows->max('total') ?? 0);
                                $pad = max(1, ($max - $min) * 0.25);
                                $lo = $min - $pad;
                                $hi = $max + $pad;
                                $points = $trendRows->map(fn (array $row, int $i): array => [
                                    'x' => $n > 1 ? 50 + $i * (500 / ($n - 1)) : 300,
                                    'y' => 190 - (((float) $row['total'] - $lo) / ($hi - $lo)) * 150,
                                    ...$row,
                                ]);
                                $polyline = $points->map(fn (array $p): string => $p['x'].','.$p['y'])->implode(' ');
                            @endphp
                            <div class="mt-5 grid gap-5">
                                <p class="text-sm text-pink-500">Perkembangan {{ $groupName }} pada Semester {{ $semester }} tiap tahun.</p>
                                @if ($points->isNotEmpty())
                                    <svg viewBox="0 0 600 240" class="h-auto w-full" role="img" aria-label="Diagram garis tren per tahun">
                                        @foreach ([40, 115, 190] as $gy)
                                            <line x1="30" y1="{{ $gy }}" x2="570" y2="{{ $gy }}" stroke="currentColor" stroke-dasharray="{{ $gy === 190 ? '0' : '4 6' }}" class="text-pink-100" />
                                        @endforeach
                                        <polyline points="{{ $polyline }}" fill="none" stroke="#ec4899" stroke-linecap="round" stroke-linejoin="round" stroke-width="3" />
                                        @foreach ($points as $p)
                                            <circle cx="{{ $p['x'] }}" cy="{{ $p['y'] }}" r="6" fill="#ec4899" stroke="white" stroke-width="3" />
                                            <text x="{{ $p['x'] }}" y="{{ $p['y'] - 14 }}" text-anchor="middle" font-size="14" font-weight="600" class="fill-rose-900">{{ $nf($p['total']) }}</text>
                                            <text x="{{ $p['x'] }}" y="220" text-anchor="middle" font-size="14" class="fill-pink-500">{{ $p['year'] }}</text>
                                        @endforeach
                                    </svg>
                                    <p class="text-xs text-pink-500">Sumbu vertikal dipotong agar perubahan antartahun terlihat; baca angka pada tiap titik.</p>
                                @else
                                    <p class="{{ $emptyChart }} mt-0">Belum ada data historis untuk kelompok data dan semester ini.</p>
                                @endif
                            </div>
                        @endif
                    </article>

                    <article class="{{ $card }} overflow-hidden">
                        <div class="border-b border-pink-100 px-5 py-4 sm:px-6">
                            <h2 class="text-xl font-semibold">Tabel rincian</h2>
                            <p class="mt-1 text-sm text-pink-500">L = laki-laki, P = perempuan. Nilai mengikuti wilayah dan periode terpilih.</p>
                        </div>
                        <div class="max-h-[560px] overflow-auto">
                            <table class="w-full text-left text-sm">
                                <thead class="sticky top-0 bg-pink-50 text-pink-600">
                                    <tr>
                                        <th scope="col" class="px-5 py-3 font-medium sm:px-6">Kategori</th>
                                        <th scope="col" class="px-3 py-3 text-right font-medium">L</th>
                                        <th scope="col" class="px-3 py-3 text-right font-medium">P</th>
                                        <th scope="col" class="px-3 py-3 text-right font-medium">Total</th>
                                        <th scope="col" class="px-5 py-3 text-right font-medium sm:px-6">%</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-pink-50">
                                    @forelse ($this->chartRows as $index => $row)
                                        <tr wire:key="table-row-{{ $index }}" class="hover:bg-pink-50/70">
                                            <th scope="row" class="px-5 py-3 font-medium sm:px-6">{{ $row['name'] }}</th>
                                            <td class="px-3 py-3 text-right tabular-nums text-rose-800">{{ $row['male'] ? $nf($row['male']) : '—' }}</td>
                                            <td class="px-3 py-3 text-right tabular-nums text-rose-800">{{ $row['female'] ? $nf($row['female']) : '—' }}</td>
                                            <td class="px-3 py-3 text-right font-semibold tabular-nums">{{ $nf($row['total']) }}</td>
                                            <td class="px-5 py-3 text-right tabular-nums text-pink-500 sm:px-6">{{ $nf(($row['total'] / $grandTotal) * 100, 1) }}</td>
                                        </tr>
                                    @empty
                                        <tr><td colspan="5" class="px-6 py-12 text-center text-pink-500">Belum ada data untuk pilihan ini.</td></tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </article>
                </section>
            </div>

            <p class="text-center text-xs leading-5 text-pink-500">
                Sumber: Dinas Kependudukan dan Pencatatan Sipil Kabupaten Jember, Data Agregat Kependudukan {{ $this->period->label_periode }}.
            </p>
        @endif
    </main>
</div>
