<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['indikator_id', 'kode_kategori', 'nama_kategori', 'urutan_tampil', 'batas_bawah', 'batas_atas', 'aktif'])]
class Category extends Model
{
    protected $table = 'dim_kategori';

    protected $primaryKey = 'kategori_id';

    /** @return BelongsTo<Indicator, $this> */
    public function indicator(): BelongsTo
    {
        return $this->belongsTo(Indicator::class, 'indikator_id', 'indikator_id');
    }
}
