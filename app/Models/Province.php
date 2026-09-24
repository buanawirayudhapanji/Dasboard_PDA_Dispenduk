<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['kode_provinsi', 'nama_provinsi', 'nama_normalisasi', 'aktif'])]
class Province extends Model
{
    protected $table = 'ref_provinsi';

    protected $primaryKey = 'provinsi_id';

    /** @return HasMany<Regency, $this> */
    public function regencies(): HasMany
    {
        return $this->hasMany(Regency::class, 'provinsi_id', 'provinsi_id');
    }
}
