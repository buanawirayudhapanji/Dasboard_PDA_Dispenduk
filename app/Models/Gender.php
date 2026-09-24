<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['kode_jenis_kelamin', 'nama_jenis_kelamin', 'urutan_tampil'])]
class Gender extends Model
{
    protected $table = 'dim_jenis_kelamin';

    protected $primaryKey = 'jenis_kelamin_id';
}
