<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['kelompok_data_id', 'satuan_id', 'kode_indikator', 'nama_indikator', 'metode_agregasi', 'deskripsi', 'aktif'])]
class Indicator extends Model
{
    protected $table = 'dim_indikator';

    protected $primaryKey = 'indikator_id';

    /** @return BelongsTo<DataGroup, $this> */
    public function dataGroup(): BelongsTo
    {
        return $this->belongsTo(DataGroup::class, 'kelompok_data_id', 'kelompok_data_id');
    }

    /** @return BelongsTo<Unit, $this> */
    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class, 'satuan_id', 'satuan_id');
    }

    /** @return HasMany<Category, $this> */
    public function categories(): HasMany
    {
        return $this->hasMany(Category::class, 'indikator_id', 'indikator_id');
    }
}
