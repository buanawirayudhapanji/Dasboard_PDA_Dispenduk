<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['nama_dokumen', 'instansi', 'tanggal_rilis', 'versi', 'lokasi_file', 'checksum_file', 'catatan'])]
class DataSource extends Model
{
    protected $table = 'dim_sumber_data';

    protected $primaryKey = 'sumber_data_id';

    protected function casts(): array
    {
        return [
            'tanggal_rilis' => 'date',
        ];
    }
}
