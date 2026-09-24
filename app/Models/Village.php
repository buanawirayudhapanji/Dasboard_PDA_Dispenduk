<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['kecamatan_id', 'kode_desa_kelurahan', 'nama_desa_kelurahan', 'nama_normalisasi', 'jenis_desa_kelurahan', 'aktif'])]
class Village extends Model
{
    protected $table = 'ref_desa_kelurahan';

    protected $primaryKey = 'desa_kelurahan_id';

    /** @return BelongsTo<District, $this> */
    public function district(): BelongsTo
    {
        return $this->belongsTo(District::class, 'kecamatan_id', 'kecamatan_id');
    }

    /** @return HasMany<PopulationFact, $this> */
    public function facts(): HasMany
    {
        return $this->hasMany(PopulationFact::class, 'desa_kelurahan_id', 'desa_kelurahan_id');
    }
}
