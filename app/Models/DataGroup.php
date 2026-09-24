<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['kode_kelompok', 'nama_kelompok', 'deskripsi', 'urutan_tampil'])]
class DataGroup extends Model
{
    protected $table = 'dim_kelompok_data';

    protected $primaryKey = 'kelompok_data_id';

    /** @return HasMany<Indicator, $this> */
    public function indicators(): HasMany
    {
        return $this->hasMany(Indicator::class, 'kelompok_data_id', 'kelompok_data_id');
    }
}
