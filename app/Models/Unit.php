<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['kode_satuan', 'nama_satuan', 'deskripsi'])]
class Unit extends Model
{
    protected $table = 'dim_satuan';

    protected $primaryKey = 'satuan_id';

    /** @return HasMany<Indicator, $this> */
    public function indicators(): HasMany
    {
        return $this->hasMany(Indicator::class, 'satuan_id', 'satuan_id');
    }
}
