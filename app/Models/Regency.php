<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['provinsi_id', 'kode_kabupaten', 'nama_kabupaten', 'nama_normalisasi', 'jenis_kabupaten', 'aktif'])]
class Regency extends Model
{
    protected $table = 'ref_kabupaten';

    protected $primaryKey = 'kabupaten_id';

    /** @return BelongsTo<Province, $this> */
    public function province(): BelongsTo
    {
        return $this->belongsTo(Province::class, 'provinsi_id', 'provinsi_id');
    }

    /** @return HasMany<District, $this> */
    public function districts(): HasMany
    {
        return $this->hasMany(District::class, 'kabupaten_id', 'kabupaten_id');
    }
}
