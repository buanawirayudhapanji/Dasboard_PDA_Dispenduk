<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['tahun', 'semester', 'tanggal_mulai', 'tanggal_selesai', 'label_periode'])]
class Period extends Model
{
    protected $table = 'dim_periode';

    protected $primaryKey = 'periode_id';

    protected function casts(): array
    {
        return [
            'tanggal_mulai' => 'date',
            'tanggal_selesai' => 'date',
        ];
    }

    /** @return HasMany<PopulationFact, $this> */
    public function facts(): HasMany
    {
        return $this->hasMany(PopulationFact::class, 'periode_id', 'periode_id');
    }
}
