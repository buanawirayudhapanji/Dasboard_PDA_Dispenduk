<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['kabupaten_id', 'kode_kecamatan', 'nama_kecamatan', 'nama_normalisasi', 'aktif'])]
class District extends Model
{
    protected $table = 'ref_kecamatan';

    protected $primaryKey = 'kecamatan_id';

    /** @return BelongsTo<Regency, $this> */
    public function regency(): BelongsTo
    {
        return $this->belongsTo(Regency::class, 'kabupaten_id', 'kabupaten_id');
    }

    /** @return HasMany<Village, $this> */
    public function villages(): HasMany
    {
        return $this->hasMany(Village::class, 'kecamatan_id', 'kecamatan_id');
    }
}
