<?php

use App\Actions\CreatePopulationXlsxTemplate;
use App\Actions\ParsePopulationXlsxImport;
use App\Models\Category;
use App\Models\DataSource;
use App\Models\District;
use App\Models\Gender;
use App\Models\Indicator;
use App\Models\Period;
use App\Models\PopulationFact;
use App\Models\Village;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithFileUploads;
use Livewire\WithPagination;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

new #[Title('Input Data Kependudukan')] class extends Component
{
    use WithFileUploads;
    use WithPagination;

    public string $inputMode = 'single';

    public ?int $managePeriodId = null;

    public ?int $manageDistrictId = null;

    public ?int $manageVillageId = null;

    public ?int $manageIndicatorId = null;

    public int $managePerPage = 50;

    /** @var array<int, string> */
    public array $managedValues = [];

    public ?int $editingPeriodId = null;

    public string $editingPeriodLabel = '';

    public string $editingPeriodStartDate = '';

    public string $editingPeriodEndDate = '';

    public ?int $periodId = null;

    public ?int $districtId = null;

    public ?int $villageId = null;

    public ?int $indicatorId = null;

    public ?int $categoryId = null;

    public ?int $genderId = null;

    public string $value = '';

    public ?int $bulkPeriodId = null;

    public ?int $templateIndicatorId = null;

    public $importFile = null;

    /** @var list<array<string, int|string>> */
    public array $importPreview = [];

    /** @var list<array{line: int, message: string}> */
    public array $importErrors = [];

    public int $importTotal = 0;

    public int $importValid = 0;

    public int $importInvalid = 0;

    public int $importNew = 0;

    public int $importUpdated = 0;

    public ?string $importToken = null;

    public int $newPeriodYear;

    public string $newPeriodSemester = '1';

    public ?string $statusMessage = null;

    public function mount(): void
    {
        $latestPeriodId = Period::query()->latest('tahun')->latest('semester')->value('periode_id');
        $this->periodId = $latestPeriodId;
        $this->bulkPeriodId = $latestPeriodId;
        $this->managePeriodId = $latestPeriodId;
        $this->inputMode = request()->routeIs('data-edit') ? 'manage' : 'single';
        $this->templateIndicatorId = Indicator::query()->where('aktif', true)->orderBy('nama_indikator')->value('indikator_id');
        $this->genderId = Gender::query()->where('kode_jenis_kelamin', 'ALL')->value('jenis_kelamin_id');
        $this->newPeriodYear = (int) now()->year;

        if ($this->inputMode === 'manage') {
            $this->syncManagedValues();
        }
    }

    public function updatedDistrictId(): void
    {
        $this->villageId = null;
        unset($this->villages);
    }

    public function showManageData(): void
    {
        $this->inputMode = 'manage';
        $this->resetPage('managed-facts-page');
        $this->syncManagedValues();
    }

    public function updatedManagePeriodId(): void
    {
        $this->resetManagedFacts();
    }

    public function updatedManageDistrictId(): void
    {
        $this->manageVillageId = null;
        $this->resetManagedFacts();
    }

    public function updatedManageVillageId(): void
    {
        $this->resetManagedFacts();
    }

    public function updatedManageIndicatorId(): void
    {
        $this->resetManagedFacts();
    }

    public function updatedManagePerPage(): void
    {
        $this->validateOnly('managePerPage', [
            'managePerPage' => ['required', 'integer', Rule::in([10, 25, 50, 100])],
        ]);

        $this->resetManagedFacts();
    }

    public function updatedPaginators(int $page, string $pageName): void
    {
        if ($pageName === 'managed-facts-page') {
            $this->syncManagedValues();
        }
    }

    public function updatedIndicatorId(): void
    {
        $this->categoryId = null;
        unset($this->categories);
    }

    public function updatedImportFile(): void
    {
        $this->resetImportPreview();
    }

    public function save(): void
    {
        abort_unless(auth()->check(), 403);

        $validated = $this->validate($this->rules(), $this->messages());
        $source = $this->staffDataSource();

        PopulationFact::query()->updateOrCreate(
            [
                'periode_id' => $validated['periodId'],
                'desa_kelurahan_id' => $validated['villageId'],
                'indikator_id' => $validated['indicatorId'],
                'kategori_id' => $validated['categoryId'],
                'jenis_kelamin_id' => $validated['genderId'],
            ],
            ['sumber_data_id' => $source->sumber_data_id, 'nilai' => $validated['value']],
        );

        $this->value = '';
        $this->statusMessage = 'Data berhasil disimpan ke database.';
        unset($this->recentFacts);
    }

    public function createPeriod(): void
    {
        abort_unless(auth()->check(), 403);

        $validated = $this->validate([
            'newPeriodYear' => [
                'required',
                'integer',
                'min:2000',
                'max:2100',
                Rule::unique('dim_periode', 'tahun')->where(
                    fn ($query) => $query->where('semester', $this->newPeriodSemester),
                ),
            ],
            'newPeriodSemester' => ['required', Rule::in(['1', '2'])],
        ], [
            'newPeriodYear.unique' => 'Periode dengan tahun dan semester tersebut sudah tersedia.',
        ]);

        $year = (int) $validated['newPeriodYear'];
        $semester = (int) $validated['newPeriodSemester'];
        $period = Period::query()->create([
            'tahun' => $year,
            'semester' => $semester,
            'tanggal_mulai' => $semester === 1 ? "{$year}-01-01" : "{$year}-07-01",
            'tanggal_selesai' => $semester === 1 ? "{$year}-06-30" : "{$year}-12-31",
            'label_periode' => 'Semester '.($semester === 1 ? 'I' : 'II')." {$year}",
        ]);

        $this->periodId = $period->periode_id;
        $this->bulkPeriodId = $period->periode_id;
        $this->statusMessage = "{$period->label_periode} berhasil ditambahkan dan langsung dipilih.";
        unset($this->periods);
    }

    public function editPeriod(int $periodId): void
    {
        abort_unless(auth()->check(), 403);

        $period = Period::query()->findOrFail($periodId);
        $this->editingPeriodId = $period->periode_id;
        $this->editingPeriodLabel = $period->label_periode;
        $this->editingPeriodStartDate = $period->tanggal_mulai->toDateString();
        $this->editingPeriodEndDate = $period->tanggal_selesai->toDateString();
        $this->resetErrorBag(['editingPeriodLabel', 'editingPeriodStartDate', 'editingPeriodEndDate']);
    }

    public function updatePeriod(): void
    {
        abort_unless(auth()->check(), 403);

        $validated = $this->validate([
            'editingPeriodId' => ['required', 'integer', Rule::exists('dim_periode', 'periode_id')],
            'editingPeriodLabel' => ['required', 'string', 'max:50'],
            'editingPeriodStartDate' => ['required', 'date'],
            'editingPeriodEndDate' => ['required', 'date', 'after_or_equal:editingPeriodStartDate'],
        ], [
            'editingPeriodEndDate.after_or_equal' => 'Tanggal akhir harus sama dengan atau setelah tanggal mulai.',
        ]);

        Period::query()
            ->whereKey($validated['editingPeriodId'])
            ->update([
                'label_periode' => $validated['editingPeriodLabel'],
                'tanggal_mulai' => $validated['editingPeriodStartDate'],
                'tanggal_selesai' => $validated['editingPeriodEndDate'],
            ]);

        $this->statusMessage = 'Periode berhasil diperbarui.';
        unset($this->periods, $this->recentFacts);
    }

    public function saveManagedFact(int $factId): void
    {
        abort_unless(auth()->check(), 403);

        $validated = $this->validate([
            'managePeriodId' => ['required', 'integer', Rule::exists('dim_periode', 'periode_id')],
            "managedValues.{$factId}" => ['required', 'numeric', 'min:0', 'max:9999999999999999'],
        ], [
            "managedValues.{$factId}.required" => 'Nilai wajib diisi.',
            "managedValues.{$factId}.numeric" => 'Nilai harus berupa angka.',
            "managedValues.{$factId}.min" => 'Nilai tidak boleh bernilai negatif.',
        ]);

        $fact = $this->managedFactsQuery()->whereKey($factId)->first();
        if ($fact === null) {
            throw ValidationException::withMessages([
                "managedValues.{$factId}" => 'Data tidak sesuai dengan filter yang sedang dipilih. Muat ulang tabel lalu coba lagi.',
            ]);
        }

        DB::transaction(function () use ($validated, $factId, $fact): void {
            PopulationFact::query()
                ->whereKey($fact->fakta_id)
                ->update(['nilai' => $validated['managedValues'][$factId]]);
        });

        $this->statusMessage = 'Nilai berhasil diperbarui.';
        $this->syncManagedValues();
        unset($this->recentFacts, $this->managedFacts);
    }

    public function downloadTemplate(CreatePopulationXlsxTemplate $template): BinaryFileResponse
    {
        abort_unless(auth()->check(), 403);

        $validated = $this->validate([
            'templateIndicatorId' => ['required', 'integer', Rule::exists('dim_indikator', 'indikator_id')],
        ]);
        $indicator = Indicator::query()->findOrFail($validated['templateIndicatorId']);
        $path = $template->handle($indicator);

        if ($path === null) {
            throw ValidationException::withMessages([
                'templateIndicatorId' => 'Belum ada struktur data historis untuk indikator ini.',
            ]);
        }

        return response()
            ->download(
                $path,
                'template-'.Str::slug($indicator->kode_indikator).'.xlsx',
                ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'],
            )
            ->deleteFileAfterSend(true);
    }

    public function previewImport(ParsePopulationXlsxImport $parser): void
    {
        abort_unless(auth()->check(), 403);

        $validated = $this->validate([
            'bulkPeriodId' => ['required', 'integer', Rule::exists('dim_periode', 'periode_id')],
            'importFile' => ['required', 'file', 'mimes:xlsx', 'extensions:xlsx', 'max:5120'],
        ], [
            'importFile.required' => 'Pilih file Excel yang akan diimpor.',
            'importFile.mimes' => 'File harus berupa Excel (.xlsx).',
            'importFile.extensions' => 'Ekstensi file harus .xlsx.',
            'importFile.max' => 'Ukuran file maksimal 5 MB.',
        ]);

        $source = $this->staffDataSource();
        $result = $parser->handle(
            $validated['importFile']->getRealPath(),
            (int) $validated['bulkPeriodId'],
            $source->sumber_data_id,
        );

        $this->forgetCachedImport();
        $this->importPreview = $result['preview'];
        $this->importErrors = $result['errors'];
        $this->importTotal = $result['total'];
        $this->importValid = count($result['rows']);
        $this->importInvalid = $result['invalid'];
        $this->importNew = $result['new'];
        $this->importUpdated = $result['updated'];

        if ($result['rows'] !== []) {
            $this->importToken = (string) Str::uuid();
            Cache::put($this->importCacheKey(), $result['rows'], now()->addMinutes(30));
        }
    }

    public function saveImport(): void
    {
        abort_unless(auth()->check(), 403);

        $rows = $this->importToken === null ? null : Cache::get($this->importCacheKey());
        if (! is_array($rows) || $rows === []) {
            $this->addError('importFile', 'Pratinjau sudah kedaluwarsa. Unggah dan periksa kembali file Excel.');

            return;
        }

        $now = now();
        DB::transaction(function () use ($rows, $now): void {
            foreach (array_chunk($rows, 500) as $chunk) {
                $chunk = array_map(
                    fn (array $row): array => [...$row, 'created_at' => $now, 'updated_at' => $now],
                    $chunk,
                );
                PopulationFact::query()->upsert(
                    $chunk,
                    ['periode_id', 'desa_kelurahan_id', 'indikator_id', 'kategori_id', 'jenis_kelamin_id'],
                    ['sumber_data_id', 'nilai', 'updated_at'],
                );
            }
        });

        $savedCount = count($rows);
        $this->forgetCachedImport();
        $this->importFile = null;
        $this->importPreview = [];
        $this->importErrors = [];
        $this->importTotal = 0;
        $this->importValid = 0;
        $this->importInvalid = 0;
        $this->importNew = 0;
        $this->importUpdated = 0;
        $this->statusMessage = number_format($savedCount, 0, ',', '.').' baris valid berhasil disimpan ke database.';
        unset($this->recentFacts);
    }

    public function resetImportPreview(): void
    {
        $this->forgetCachedImport();
        $this->importPreview = [];
        $this->importErrors = [];
        $this->importTotal = 0;
        $this->importValid = 0;
        $this->importInvalid = 0;
        $this->importNew = 0;
        $this->importUpdated = 0;
        $this->resetErrorBag('importFile');
    }

    #[Computed]
    public function periods(): Collection
    {
        return Period::query()->orderByDesc('tahun')->orderByDesc('semester')->get();
    }

    #[Computed]
    public function districts(): Collection
    {
        return District::query()->where('aktif', true)->orderBy('nama_kecamatan')->get();
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
    public function managedVillages(): Collection
    {
        return Village::query()
            ->when($this->manageDistrictId, fn (Builder $query) => $query->where('kecamatan_id', $this->manageDistrictId))
            ->where('aktif', true)
            ->orderBy('nama_desa_kelurahan')
            ->get();
    }

    #[Computed]
    public function indicators(): Collection
    {
        return Indicator::query()->with('dataGroup')->where('aktif', true)->orderBy('nama_indikator')->get();
    }

    #[Computed]
    public function categories(): Collection
    {
        return Category::query()
            ->when($this->indicatorId, fn (Builder $query) => $query->where('indikator_id', $this->indicatorId))
            ->where('aktif', true)
            ->orderBy('urutan_tampil')
            ->get();
    }

    #[Computed]
    public function genders(): Collection
    {
        return Gender::query()->orderBy('urutan_tampil')->get();
    }

    #[Computed]
    public function recentFacts(): Collection
    {
        return PopulationFact::query()
            ->with(['period', 'village.district', 'indicator', 'category', 'gender'])
            ->latest('updated_at')
            ->limit(10)
            ->get();
    }

    #[Computed]
    public function managedFacts(): LengthAwarePaginator
    {
        return $this->managedFactsQuery()
            ->with(['village.district', 'indicator', 'category', 'gender'])
            ->paginate($this->managePerPage, ['*'], 'managed-facts-page');
    }

    private function staffDataSource(): DataSource
    {
        return DataSource::query()->firstOrCreate(
            ['nama_dokumen' => 'Input Petugas'],
            ['instansi' => 'Dinas Kependudukan dan Pencatatan Sipil Kabupaten Jember', 'catatan' => 'Entri manual atau impor massal melalui dashboard petugas.'],
        );
    }

    private function resetManagedFacts(): void
    {
        $this->resetPage('managed-facts-page');
        unset($this->managedFacts);
        $this->syncManagedValues();
    }

    private function syncManagedValues(): void
    {
        $this->managedValues = $this->managedFactsQuery()
            ->paginate($this->managePerPage, ['*'], 'managed-facts-page')
            ->mapWithKeys(fn (PopulationFact $fact): array => [$fact->fakta_id => $fact->nilai])
            ->all();
    }

    /** @return Builder<PopulationFact> */
    private function managedFactsQuery(): Builder
    {
        return PopulationFact::query()
            ->when($this->managePeriodId, fn (Builder $query) => $query->where('periode_id', $this->managePeriodId))
            ->when($this->manageDistrictId, fn (Builder $query) => $query->whereHas('village', fn (Builder $query) => $query->where('kecamatan_id', $this->manageDistrictId)))
            ->when($this->manageVillageId, fn (Builder $query) => $query->where('desa_kelurahan_id', $this->manageVillageId))
            ->when($this->manageIndicatorId, fn (Builder $query) => $query->where('indikator_id', $this->manageIndicatorId))
            ->orderBy('desa_kelurahan_id')
            ->orderBy('indikator_id')
            ->orderBy('kategori_id')
            ->orderBy('jenis_kelamin_id');
    }

    private function importCacheKey(): string
    {
        return 'population-import:'.auth()->id().':'.$this->importToken;
    }

    private function forgetCachedImport(): void
    {
        if ($this->importToken !== null) {
            Cache::forget($this->importCacheKey());
        }

        $this->importToken = null;
    }

    /** @return array<string, mixed> */
    protected function rules(): array
    {
        return [
            'periodId' => ['required', 'integer', Rule::exists('dim_periode', 'periode_id')],
            'districtId' => ['required', 'integer', Rule::exists('ref_kecamatan', 'kecamatan_id')],
            'villageId' => [
                'required',
                'integer',
                Rule::exists('ref_desa_kelurahan', 'desa_kelurahan_id')->where(
                    fn ($query) => $query->where('kecamatan_id', $this->districtId),
                ),
            ],
            'indicatorId' => ['required', 'integer', Rule::exists('dim_indikator', 'indikator_id')],
            'categoryId' => [
                'required',
                'integer',
                Rule::exists('dim_kategori', 'kategori_id')->where(
                    fn ($query) => $query->where('indikator_id', $this->indicatorId),
                ),
            ],
            'genderId' => ['required', 'integer', Rule::exists('dim_jenis_kelamin', 'jenis_kelamin_id')],
            'value' => ['required', 'numeric', 'min:0', 'max:9999999999999999'],
        ];
    }

    /** @return array<string, string> */
    protected function messages(): array
    {
        return [
            'required' => ':attribute wajib dipilih atau diisi.',
            'numeric' => ':attribute harus berupa angka.',
            'min' => ':attribute tidak boleh bernilai negatif.',
            'exists' => 'Pilihan :attribute tidak valid.',
        ];
    }
};
?>

<div class="flex h-full w-full flex-1 flex-col gap-6 bg-[#fff6fa] p-4 text-rose-950 sm:p-6">
    <div class="flex flex-wrap items-end justify-between gap-4">
        <div>
            <flux:heading size="xl">{{ $inputMode === 'manage' ? 'Edit Data Kependudukan' : 'Input Data Kependudukan' }}</flux:heading>
            <flux:text class="mt-2">{{ $inputMode === 'manage' ? 'Ubah nilai data tersimpan dan periode pelaporan.' : 'Gunakan input satuan untuk koreksi cepat atau impor massal untuk memasukkan banyak data sekaligus.' }}</flux:text>
        </div>
        <flux:button :href="route('home')" icon="chart-bar" wire:navigate class="border-pink-200 bg-white text-pink-600 hover:bg-pink-50">Lihat dashboard publik</flux:button>
    </div>

    @if ($statusMessage)
        <flux:callout icon="check-circle" color="emerald" wire:key="save-success-{{ md5($statusMessage) }}">
            <flux:callout.heading>Berhasil</flux:callout.heading>
            <flux:callout.text>{{ $statusMessage }}</flux:callout.text>
        </flux:callout>
    @endif

    @if ($inputMode !== 'manage')
    <details class="rounded-2xl border border-pink-100 bg-white shadow-sm shadow-pink-100/50">
        <summary class="cursor-pointer list-none px-5 py-4 hover:bg-pink-50/50">
            <div class="flex items-center justify-between gap-4">
                <div>
                    <flux:heading size="lg">Tambah periode baru</flux:heading>
                    <flux:text class="mt-1">Tanggal awal dan akhir akan dibuat otomatis berdasarkan semester.</flux:text>
                </div>
                <span class="grid size-7 place-items-center rounded-lg border border-pink-200 bg-pink-50 text-pink-500"><flux:icon.plus class="size-4" /></span>
            </div>
        </summary>
        <form wire:submit="createPeriod" class="grid gap-4 border-t border-pink-100 px-5 py-4 sm:grid-cols-[1fr_1fr_auto] sm:items-end">
            <flux:field>
                <flux:label>Tahun</flux:label>
                <flux:input wire:model="newPeriodYear" type="number" min="2000" max="2100" />
                <flux:error name="newPeriodYear" />
            </flux:field>
            <flux:field>
                <flux:label>Semester</flux:label>
                <flux:select wire:model="newPeriodSemester">
                    <option value="1">Semester I</option>
                    <option value="2">Semester II</option>
                </flux:select>
                <flux:error name="newPeriodSemester" />
            </flux:field>
            <flux:button type="submit" variant="primary" icon="plus" wire:loading.attr="disabled">Tambahkan periode</flux:button>
        </form>
    </details>

    @endif

    @if ($inputMode === 'manage')
    <details class="rounded-2xl border border-pink-100 bg-white shadow-sm shadow-pink-100/50">
        <summary class="cursor-pointer list-none px-5 py-4 hover:bg-pink-50/50">
            <div class="flex items-center justify-between gap-4">
                <div>
                    <flux:heading size="lg">Edit periode</flux:heading>
                    <flux:text class="mt-1">Ubah nama dan rentang tanggal periode. Tahun serta semester tetap agar data yang sudah tersimpan tidak tertukar.</flux:text>
                </div>
                <span class="grid size-7 place-items-center rounded-lg border border-pink-200 bg-pink-50 text-pink-500"><flux:icon.calendar-days class="size-4" /></span>
            </div>
        </summary>
        <div class="grid gap-6 border-t border-pink-100 px-5 py-4 lg:grid-cols-[0.8fr_1.2fr]">
            <div class="max-h-64 overflow-y-auto rounded-xl border border-pink-100">
                @foreach ($this->periods as $period)
                    <button type="button" wire:click="editPeriod({{ $period->periode_id }})" wire:key="period-edit-{{ $period->periode_id }}" class="flex w-full items-center justify-between gap-3 border-b border-pink-50 px-4 py-3 text-left last:border-b-0 hover:bg-pink-50">
                        <span>
                            <span class="block font-medium text-rose-950">{{ $period->label_periode }}</span>
                            <span class="block text-xs text-pink-500">{{ $period->tanggal_mulai->format('d M Y') }} – {{ $period->tanggal_selesai->format('d M Y') }}</span>
                        </span>
                        <flux:icon.pencil-square class="size-4 text-pink-400" />
                    </button>
                @endforeach
            </div>

            @if ($editingPeriodId)
                <form wire:submit="updatePeriod" class="grid content-start gap-4">
                    <div class="grid gap-4 sm:grid-cols-2">
                        <flux:field class="sm:col-span-2">
                            <flux:label>Nama periode</flux:label>
                            <flux:input wire:model="editingPeriodLabel" maxlength="50" />
                            <flux:error name="editingPeriodLabel" />
                        </flux:field>
                        <flux:field>
                            <flux:label>Tanggal mulai</flux:label>
                            <flux:input wire:model="editingPeriodStartDate" type="date" />
                            <flux:error name="editingPeriodStartDate" />
                        </flux:field>
                        <flux:field>
                            <flux:label>Tanggal selesai</flux:label>
                            <flux:input wire:model="editingPeriodEndDate" type="date" />
                            <flux:error name="editingPeriodEndDate" />
                        </flux:field>
                    </div>
                    <div class="flex justify-end">
                        <flux:button type="submit" icon="check" variant="primary" wire:loading.attr="disabled">Simpan periode</flux:button>
                    </div>
                </form>
            @else
                <div class="grid place-items-center rounded-xl border border-dashed border-pink-200 bg-pink-50/40 px-6 py-10 text-center text-sm text-pink-500">
                    Pilih periode di sebelah kiri untuk mengubahnya.
                </div>
            @endif
        </div>
    </details>
    @endif

    @if ($inputMode !== 'manage')
    <div class="inline-flex w-fit rounded-xl bg-pink-50 p-1" role="tablist" aria-label="Mode input data">
        <button type="button" wire:click="$set('inputMode', 'single')" class="rounded-lg px-4 py-2 text-sm font-medium transition {{ $inputMode === 'single' ? 'border border-pink-100 bg-white text-rose-950 shadow-sm' : 'text-pink-600 hover:bg-pink-100' }}">
            Input satuan
        </button>
        <button type="button" wire:click="$set('inputMode', 'bulk')" class="rounded-lg px-4 py-2 text-sm font-medium transition {{ $inputMode === 'bulk' ? 'border border-pink-100 bg-white text-rose-950 shadow-sm' : 'text-pink-600 hover:bg-pink-100' }}">
            Import massal
        </button>
    </div>
    @endif

    @if ($inputMode === 'single')
        <div class="grid gap-6 xl:grid-cols-[0.8fr_1.2fr]">
            <form wire:submit="save" class="grid content-start gap-5 rounded-2xl border border-pink-100 bg-white p-6 shadow-sm shadow-pink-100/50">
                <div class="grid gap-4 sm:grid-cols-2">
                    <flux:field>
                        <flux:label>Periode</flux:label>
                        <flux:select wire:model="periodId">
                            <option value="">Pilih periode</option>
                            @foreach ($this->periods as $period)
                                <option value="{{ $period->periode_id }}">{{ $period->label_periode }}</option>
                            @endforeach
                        </flux:select>
                        <flux:error name="periodId" />
                    </flux:field>
                    <flux:field>
                        <flux:label>Kecamatan</flux:label>
                        <flux:select wire:model.live="districtId">
                            <option value="">Pilih kecamatan</option>
                            @foreach ($this->districts as $district)
                                <option value="{{ $district->kecamatan_id }}">{{ $district->nama_kecamatan }}</option>
                            @endforeach
                        </flux:select>
                        <flux:error name="districtId" />
                    </flux:field>
                </div>
                <flux:field>
                    <flux:label>Desa/Kelurahan</flux:label>
                    <flux:select wire:model="villageId" wire:key="entry-villages-{{ $districtId ?? 'none' }}">
                        <option value="">Pilih desa/kelurahan</option>
                        @foreach ($this->villages as $village)
                            <option value="{{ $village->desa_kelurahan_id }}">{{ $village->nama_desa_kelurahan }}</option>
                        @endforeach
                    </flux:select>
                    <flux:error name="villageId" />
                </flux:field>
                <flux:field>
                    <flux:label>Indikator</flux:label>
                    <flux:select wire:model.live="indicatorId">
                        <option value="">Pilih indikator</option>
                        @foreach ($this->indicators as $indicator)
                            <option value="{{ $indicator->indikator_id }}">{{ $indicator->dataGroup->nama_kelompok }} — {{ $indicator->nama_indikator }}</option>
                        @endforeach
                    </flux:select>
                    <flux:error name="indicatorId" />
                </flux:field>
                <div class="grid gap-4 sm:grid-cols-2">
                    <flux:field>
                        <flux:label>Kategori</flux:label>
                        <flux:select wire:model="categoryId" wire:key="entry-categories-{{ $indicatorId ?? 'none' }}">
                            <option value="">Pilih kategori</option>
                            @foreach ($this->categories as $category)
                                <option value="{{ $category->kategori_id }}">{{ $category->nama_kategori }}</option>
                            @endforeach
                        </flux:select>
                        <flux:error name="categoryId" />
                    </flux:field>
                    <flux:field>
                        <flux:label>Jenis Kelamin</flux:label>
                        <flux:select wire:model="genderId">
                            @foreach ($this->genders as $gender)
                                <option value="{{ $gender->jenis_kelamin_id }}">{{ $gender->nama_jenis_kelamin }}</option>
                            @endforeach
                        </flux:select>
                        <flux:error name="genderId" />
                    </flux:field>
                </div>
                <flux:field>
                    <flux:label>Nilai</flux:label>
                    <flux:input wire:model="value" type="number" min="0" step="0.0001" placeholder="0" />
                    <flux:error name="value" />
                </flux:field>
                <div class="flex justify-end border-t border-pink-100 pt-5">
                    <flux:button type="submit" variant="primary" icon="inbox-arrow-down" wire:loading.attr="disabled">
                        <span wire:loading.remove wire:target="save">Simpan ke database</span>
                        <span wire:loading wire:target="save">Menyimpan…</span>
                    </flux:button>
                </div>
            </form>

            <section class="overflow-hidden rounded-2xl border border-pink-100 bg-white shadow-sm shadow-pink-100/50">
                <div class="border-b border-pink-100 px-5 py-4">
                    <flux:heading size="lg">Data terbaru</flux:heading>
                    <flux:text class="mt-1">Sepuluh nilai yang terakhir diperbarui.</flux:text>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-sm">
                        <thead class="bg-pink-50/60 text-xs uppercase tracking-wide text-pink-600">
                            <tr>
                                <th class="px-5 py-3 font-medium">Wilayah</th>
                                <th class="px-4 py-3 font-medium">Indikator</th>
                                <th class="px-4 py-3 font-medium">Kategori</th>
                                <th class="px-4 py-3 font-medium">JK</th>
                                <th class="px-5 py-3 text-right font-medium">Nilai</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-pink-50">
                            @forelse ($this->recentFacts as $fact)
                                <tr wire:key="recent-fact-{{ $fact->fakta_id }}" class="transition-colors hover:bg-pink-50/50">
                                    <td class="px-5 py-3">
                                        <p class="font-medium text-rose-950">{{ $fact->village->nama_desa_kelurahan }}</p>
                                        <p class="text-xs text-pink-500">{{ $fact->village->district->nama_kecamatan }} · {{ $fact->period->label_periode }}</p>
                                    </td>
                                    <td class="px-4 py-3 text-rose-800">{{ $fact->indicator->nama_indikator }}</td>
                                    <td class="px-4 py-3 text-pink-500">{{ $fact->category->nama_kategori }}</td>
                                    <td class="px-4 py-3"><flux:badge size="sm">{{ $fact->gender->kode_jenis_kelamin }}</flux:badge></td>
                                    <td class="px-5 py-3 text-right font-semibold tabular-nums">{{ number_format((float) $fact->nilai, 0, ',', '.') }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="5" class="px-5 py-10 text-center text-pink-500">Belum ada data.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </section>
        </div>
    @elseif ($inputMode === 'bulk')
        <div class="grid gap-6 xl:grid-cols-[0.75fr_1.25fr]">
            <section class="grid content-start gap-6 rounded-2xl border border-pink-100 bg-white p-6 shadow-sm shadow-pink-100/50">
                <div>
                    <flux:heading size="lg">1. Unduh template</flux:heading>
                    <flux:text class="mt-1">Pilih indikator. Template berisi seluruh desa dan kombinasi kategori yang sesuai data historis.</flux:text>
                </div>
                <flux:field>
                    <flux:label>Indikator template</flux:label>
                    <flux:select wire:model="templateIndicatorId">
                        <option value="">Pilih indikator</option>
                        @foreach ($this->indicators as $indicator)
                            <option value="{{ $indicator->indikator_id }}">{{ $indicator->dataGroup->nama_kelompok }} — {{ $indicator->nama_indikator }}</option>
                        @endforeach
                    </flux:select>
                    <flux:error name="templateIndicatorId" />
                </flux:field>
                <flux:button wire:click="downloadTemplate" icon="arrow-down-tray" wire:loading.attr="disabled">
                    Unduh template Excel
                </flux:button>
                <flux:callout icon="information-circle" color="blue">
                    <flux:callout.text>Buka template di Excel, isi kolom <strong>Nilai</strong>, lalu unggah kembali tanpa mengubah struktur file.</flux:callout.text>
                </flux:callout>

                <div class="border-t border-pink-100 pt-6">
                    <flux:heading size="lg">2. Unggah dan periksa</flux:heading>
                    <flux:text class="mt-1">Maksimal 10.000 baris atau 5 MB per unggahan.</flux:text>
                </div>
                <form wire:submit="previewImport" class="grid gap-5">
                    <flux:field>
                        <flux:label>Periode tujuan</flux:label>
                        <flux:select wire:model="bulkPeriodId">
                            <option value="">Pilih periode</option>
                            @foreach ($this->periods as $period)
                                <option value="{{ $period->periode_id }}">{{ $period->label_periode }}</option>
                            @endforeach
                        </flux:select>
                        <flux:error name="bulkPeriodId" />
                    </flux:field>
                    <flux:field>
                        <flux:label>File Excel</flux:label>
                        <input wire:model="importFile" type="file" accept=".xlsx,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet" class="block w-full rounded-xl border border-pink-200 bg-pink-50/50 px-3 py-2 text-sm text-rose-800 file:mr-4 file:rounded-lg file:border-0 file:bg-white file:px-3 file:py-2 file:text-sm file:font-medium file:text-pink-600" />
                        <flux:error name="importFile" />
                    </flux:field>
                    <flux:button type="submit" variant="primary" icon="magnifying-glass" wire:loading.attr="disabled">
                        <span wire:loading.remove wire:target="previewImport">Periksa file</span>
                        <span wire:loading wire:target="previewImport">Memeriksa…</span>
                    </flux:button>
                </form>
            </section>

            <section class="overflow-hidden rounded-2xl border border-pink-100 bg-white shadow-sm shadow-pink-100/50">
                <div class="border-b border-pink-100 px-5 py-4">
                    <flux:heading size="lg">Pratinjau impor</flux:heading>
                    <flux:text class="mt-1">Data belum disimpan sampai tombol simpan ditekan.</flux:text>
                </div>

                @if ($importTotal > 0 || $importErrors !== [])
                    <div class="grid grid-cols-2 gap-3 border-b border-pink-100 p-5 sm:grid-cols-4">
                        <div class="rounded-xl border border-pink-100 bg-pink-50/60 p-4">
                            <p class="text-xs uppercase tracking-wide text-pink-500">Dibaca</p>
                            <p class="mt-1 text-2xl font-semibold tabular-nums">{{ number_format($importTotal, 0, ',', '.') }}</p>
                        </div>
                        <div class="rounded-xl border border-emerald-200 bg-emerald-50 p-4">
                            <p class="text-xs uppercase tracking-wide text-emerald-700">Data baru</p>
                            <p class="mt-1 text-2xl font-semibold tabular-nums text-emerald-700">{{ number_format($importNew, 0, ',', '.') }}</p>
                        </div>
                        <div class="rounded-xl border border-amber-200 bg-amber-50 p-4">
                            <p class="text-xs uppercase tracking-wide text-amber-700">Akan ditimpa</p>
                            <p class="mt-1 text-2xl font-semibold tabular-nums text-amber-700">{{ number_format($importUpdated, 0, ',', '.') }}</p>
                        </div>
                        <div class="rounded-xl border border-red-200 bg-red-50 p-4">
                            <p class="text-xs uppercase tracking-wide text-red-700">Bermasalah</p>
                            <p class="mt-1 text-2xl font-semibold tabular-nums text-red-700">{{ number_format($importInvalid, 0, ',', '.') }}</p>
                        </div>
                    </div>

                    @if ($importErrors !== [])
                        <div class="border-b border-pink-100 bg-red-50 px-5 py-4">
                            <p class="font-medium text-red-800">Periksa baris berikut sebelum mengimpor ulang:</p>
                            <ul class="mt-3 max-h-40 space-y-1 overflow-y-auto text-sm text-red-700">
                                @foreach ($importErrors as $error)
                                    <li>Baris {{ $error['line'] }}: {{ $error['message'] }}</li>
                                @endforeach
                            </ul>
                        </div>
                    @endif

                    @if ($importPreview !== [])
                        <div class="overflow-x-auto">
                            <table class="w-full text-left text-sm">
                                <thead class="bg-pink-50/60 text-xs uppercase tracking-wide text-pink-600">
                                    <tr>
                                        <th class="px-5 py-3 font-medium">Baris</th>
                                        <th class="px-4 py-3 font-medium">Wilayah</th>
                                        <th class="px-4 py-3 font-medium">Indikator/Kategori</th>
                                        <th class="px-4 py-3 font-medium">JK</th>
                                        <th class="px-4 py-3 font-medium">Aksi</th>
                                        <th class="px-5 py-3 text-right font-medium">Nilai</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-pink-50">
                                    @foreach ($importPreview as $row)
                                        <tr wire:key="preview-row-{{ $row['line'] }}" class="hover:bg-pink-50/50">
                                            <td class="px-5 py-3 text-pink-500">{{ $row['line'] }}</td>
                                            <td class="px-4 py-3">
                                                <p class="font-medium">{{ $row['village'] }}</p>
                                                <p class="text-xs text-pink-500">{{ $row['district'] }}</p>
                                            </td>
                                            <td class="px-4 py-3">
                                                <p>{{ $row['indicator'] }}</p>
                                                <p class="text-xs text-pink-500">{{ $row['category'] }}</p>
                                            </td>
                                            <td class="px-4 py-3">{{ $row['gender'] }}</td>
                                            <td class="px-4 py-3"><span class="rounded-full px-2 py-1 text-xs font-semibold {{ $row['action'] === 'Perbarui' ? 'bg-amber-100 text-amber-700' : 'bg-emerald-100 text-emerald-700' }}">{{ $row['action'] }}</span></td>
                                            <td class="px-5 py-3 text-right font-semibold tabular-nums">{{ number_format((float) $row['value'], 0, ',', '.') }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                        <div class="flex flex-wrap items-center justify-between gap-4 border-t border-pink-100 px-5 py-4">
                            <flux:text>{{ number_format($importNew, 0, ',', '.') }} data baru akan ditambahkan dan {{ number_format($importUpdated, 0, ',', '.') }} data akan diperbarui. Baris bermasalah dilewati.</flux:text>
                            <flux:button wire:click="saveImport" variant="primary" icon="inbox-arrow-down" wire:loading.attr="disabled">
                                <span wire:loading.remove wire:target="saveImport">Simpan baris valid</span>
                                <span wire:loading wire:target="saveImport">Menyimpan…</span>
                            </flux:button>
                        </div>
                    @endif
                @else
                    <div class="grid min-h-72 place-items-center px-6 py-12 text-center">
                        <div>
                            <span class="mx-auto grid size-16 place-items-center rounded-2xl border border-pink-100 bg-pink-50"><flux:icon.document-magnifying-glass class="size-8 text-pink-300" /></span>
                            <p class="mt-4 font-medium text-rose-950">Belum ada file yang diperiksa</p>
                            <p class="mt-1 text-sm text-pink-500">Unggah Excel untuk melihat ringkasan validasi dan contoh data.</p>
                        </div>
                    </div>
                @endif
            </section>
        </div>
    @else
        <section class="grid gap-6 overflow-hidden rounded-2xl border border-pink-100 bg-white shadow-sm shadow-pink-100/50">
            <div class="flex flex-wrap items-end justify-between gap-4 px-5 pt-5">
                <div>
                    <flux:heading size="lg">Edit data tersimpan</flux:heading>
                    <flux:text class="mt-1">Pilih filter, ubah nilai langsung di tabel, lalu simpan masing-masing baris yang diedit.</flux:text>
                </div>
                <span class="rounded-full bg-pink-500 px-3 py-1.5 text-xs font-bold text-white shadow-sm">{{ number_format($this->managedFacts->total(), 0, ',', '.') }} data ditemukan</span>
            </div>

            <div class="grid gap-4 border-y border-pink-100 px-5 py-4 sm:grid-cols-2 xl:grid-cols-5">
                <flux:field>
                    <flux:label>Periode</flux:label>
                    <flux:select wire:model.live="managePeriodId">
                        <option value="">Pilih periode</option>
                        @foreach ($this->periods as $period)
                            <option value="{{ $period->periode_id }}">{{ $period->label_periode }}</option>
                        @endforeach
                    </flux:select>
                    <flux:error name="managePeriodId" />
                </flux:field>
                <flux:field>
                    <flux:label>Kecamatan</flux:label>
                    <flux:select wire:model.live="manageDistrictId">
                        <option value="">Semua kecamatan</option>
                        @foreach ($this->districts as $district)
                            <option value="{{ $district->kecamatan_id }}">{{ $district->nama_kecamatan }}</option>
                        @endforeach
                    </flux:select>
                </flux:field>
                <flux:field>
                    <flux:label>Desa/Kelurahan</flux:label>
                    <flux:select wire:model.live="manageVillageId" wire:key="manage-villages-{{ $manageDistrictId ?? 'all' }}">
                        <option value="">Semua desa/kelurahan</option>
                        @foreach ($this->managedVillages as $village)
                            <option value="{{ $village->desa_kelurahan_id }}">{{ $village->nama_desa_kelurahan }}</option>
                        @endforeach
                    </flux:select>
                </flux:field>
                <flux:field>
                    <flux:label>Indikator</flux:label>
                    <flux:select wire:model.live="manageIndicatorId">
                        <option value="">Semua indikator</option>
                        @foreach ($this->indicators as $indicator)
                            <option value="{{ $indicator->indikator_id }}">{{ $indicator->nama_indikator }}</option>
                        @endforeach
                    </flux:select>
                </flux:field>
                <flux:field>
                    <flux:label>Data per halaman</flux:label>
                    <flux:select wire:model.live="managePerPage">
                        <option value="10">10 data</option>
                        <option value="25">25 data</option>
                        <option value="50">50 data</option>
                        <option value="100">100 data</option>
                    </flux:select>
                    <flux:error name="managePerPage" />
                </flux:field>
            </div>

            <div class="mx-5 overflow-x-auto rounded-xl border border-pink-100">
                    <table class="w-full text-left text-sm">
                        <thead class="bg-pink-50/60 text-xs uppercase tracking-wide text-pink-600">
                            <tr>
                                <th class="px-5 py-3 font-medium">Wilayah</th>
                                <th class="px-4 py-3 font-medium">Indikator</th>
                                <th class="px-4 py-3 font-medium">Kategori</th>
                                <th class="px-4 py-3 font-medium">JK</th>
                                <th class="min-w-40 px-5 py-3 text-right font-medium">Nilai</th>
                                <th class="px-5 py-3 text-right font-medium">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-pink-50">
                            @forelse ($this->managedFacts as $fact)
                                <tr wire:key="managed-fact-{{ $fact->fakta_id }}" class="transition-colors hover:bg-pink-50/50">
                                    <td class="px-5 py-3">
                                        <p class="font-medium text-rose-950">{{ $fact->village->nama_desa_kelurahan }}</p>
                                        <p class="text-xs text-pink-500">{{ $fact->village->district->nama_kecamatan }}</p>
                                    </td>
                                    <td class="px-4 py-3 text-rose-800">{{ $fact->indicator->nama_indikator }}</td>
                                    <td class="px-4 py-3 text-pink-500">{{ $fact->category->nama_kategori }}</td>
                                    <td class="px-4 py-3"><flux:badge size="sm">{{ $fact->gender->kode_jenis_kelamin }}</flux:badge></td>
                                    <td class="px-5 py-3">
                                        <flux:input wire:model="managedValues.{{ $fact->fakta_id }}" type="number" min="0" step="0.0001" class="text-right tabular-nums" />
                                        <flux:error name="managedValues.{{ $fact->fakta_id }}" />
                                    </td>
                                    <td class="px-5 py-3 text-right">
                                        <flux:button type="button" size="sm" wire:click="saveManagedFact({{ $fact->fakta_id }})" icon="check" wire:loading.attr="disabled" wire:target="saveManagedFact({{ $fact->fakta_id }})">
                                            Simpan
                                        </flux:button>
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="6" class="px-5 py-10 text-center text-pink-500">Tidak ada data sesuai filter.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
            </div>

            <div class="mx-5 mb-5 mt-4">{{ $this->managedFacts->links() }}</div>
        </section>
    @endif
</div>
