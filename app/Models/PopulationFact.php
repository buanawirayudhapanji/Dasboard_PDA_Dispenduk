<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['periode_id', 'desa_kelurahan_id', 'indikator_id', 'kategori_id', 'jenis_kelamin_id', 'sumber_data_id', 'nilai'])]
class PopulationFact extends Model
{
    protected $table = 'fact_kependudukan';

    protected $primaryKey = 'fakta_id';

    protected function casts(): array
    {
        return [
            'nilai' => 'decimal:4',
        ];
    }

    /** @return BelongsTo<Period, $this> */
    public function period(): BelongsTo
    {
        return $this->belongsTo(Period::class, 'periode_id', 'periode_id');
    }

    /** @return BelongsTo<Village, $this> */
    public function village(): BelongsTo
    {
        return $this->belongsTo(Village::class, 'desa_kelurahan_id', 'desa_kelurahan_id');
    }

    /** @return BelongsTo<Indicator, $this> */
    public function indicator(): BelongsTo
    {
        return $this->belongsTo(Indicator::class, 'indikator_id', 'indikator_id');
    }

    /** @return BelongsTo<Category, $this> */
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class, 'kategori_id', 'kategori_id');
    }

    /** @return BelongsTo<Gender, $this> */
    public function gender(): BelongsTo
    {
        return $this->belongsTo(Gender::class, 'jenis_kelamin_id', 'jenis_kelamin_id');
    }

    /** @return BelongsTo<DataSource, $this> */
    public function source(): BelongsTo
    {
        return $this->belongsTo(DataSource::class, 'sumber_data_id', 'sumber_data_id');
    }
}
