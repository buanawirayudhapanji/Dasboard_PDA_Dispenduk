<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('ref_provinsi', function (Blueprint $table) {
            $table->increments('provinsi_id');
            $table->string('kode_provinsi', 10)->unique();
            $table->string('nama_provinsi', 100);
            $table->string('nama_normalisasi', 100)->index();
            $table->boolean('aktif')->default(true);
            $table->timestamps();
        });

        Schema::create('ref_kabupaten', function (Blueprint $table) {
            $table->increments('kabupaten_id');
            $table->unsignedInteger('provinsi_id');
            $table->string('kode_kabupaten', 15)->unique();
            $table->string('nama_kabupaten', 100);
            $table->string('nama_normalisasi', 100);
            $table->string('jenis_kabupaten', 20);
            $table->boolean('aktif')->default(true);
            $table->timestamps();

            $table->foreign('provinsi_id')->references('provinsi_id')->on('ref_provinsi')->cascadeOnDelete();
            $table->unique(['provinsi_id', 'nama_normalisasi'], 'uq_kabupaten_dalam_provinsi');
        });

        Schema::create('ref_kecamatan', function (Blueprint $table) {
            $table->increments('kecamatan_id');
            $table->unsignedInteger('kabupaten_id');
            $table->string('kode_kecamatan', 20)->unique();
            $table->string('nama_kecamatan', 100);
            $table->string('nama_normalisasi', 100);
            $table->boolean('aktif')->default(true);
            $table->timestamps();

            $table->foreign('kabupaten_id')->references('kabupaten_id')->on('ref_kabupaten')->cascadeOnDelete();
            $table->unique(['kabupaten_id', 'nama_normalisasi'], 'uq_kecamatan_dalam_kabupaten');
        });

        Schema::create('ref_desa_kelurahan', function (Blueprint $table) {
            $table->id('desa_kelurahan_id');
            $table->unsignedInteger('kecamatan_id');
            $table->string('kode_desa_kelurahan', 25)->unique();
            $table->string('nama_desa_kelurahan', 150);
            $table->string('nama_normalisasi', 150);
            $table->string('jenis_desa_kelurahan', 20)->nullable();
            $table->boolean('aktif')->default(true);
            $table->timestamps();

            $table->foreign('kecamatan_id')->references('kecamatan_id')->on('ref_kecamatan')->cascadeOnDelete();
            $table->unique(['kecamatan_id', 'nama_normalisasi'], 'uq_desa_dalam_kecamatan');
        });

        Schema::create('dim_periode', function (Blueprint $table) {
            $table->increments('periode_id');
            $table->unsignedSmallInteger('tahun');
            $table->unsignedTinyInteger('semester');
            $table->date('tanggal_mulai');
            $table->date('tanggal_selesai');
            $table->string('label_periode', 50);
            $table->timestamps();

            $table->unique(['tahun', 'semester'], 'uq_tahun_semester');
        });

        Schema::create('dim_kelompok_data', function (Blueprint $table) {
            $table->smallIncrements('kelompok_data_id');
            $table->string('kode_kelompok', 50)->unique();
            $table->string('nama_kelompok', 150);
            $table->text('deskripsi')->nullable();
            $table->unsignedSmallInteger('urutan_tampil');
            $table->timestamps();
        });

        Schema::create('dim_satuan', function (Blueprint $table) {
            $table->smallIncrements('satuan_id');
            $table->string('kode_satuan', 30)->unique();
            $table->string('nama_satuan', 100);
            $table->text('deskripsi')->nullable();
            $table->timestamps();
        });

        Schema::create('dim_indikator', function (Blueprint $table) {
            $table->increments('indikator_id');
            $table->unsignedSmallInteger('kelompok_data_id');
            $table->unsignedSmallInteger('satuan_id');
            $table->string('kode_indikator', 80);
            $table->string('nama_indikator', 200);
            $table->string('metode_agregasi', 30)->default('SUM');
            $table->text('deskripsi')->nullable();
            $table->boolean('aktif')->default(true);
            $table->timestamps();

            $table->foreign('kelompok_data_id')->references('kelompok_data_id')->on('dim_kelompok_data')->cascadeOnDelete();
            $table->foreign('satuan_id')->references('satuan_id')->on('dim_satuan')->restrictOnDelete();
            $table->unique(['kelompok_data_id', 'kode_indikator'], 'uq_indikator_dalam_kelompok');
        });

        Schema::create('dim_kategori', function (Blueprint $table) {
            $table->increments('kategori_id');
            $table->unsignedInteger('indikator_id');
            $table->string('kode_kategori', 80);
            $table->string('nama_kategori', 200);
            $table->unsignedSmallInteger('urutan_tampil');
            $table->decimal('batas_bawah', 10, 2)->nullable();
            $table->decimal('batas_atas', 10, 2)->nullable();
            $table->boolean('aktif')->default(true);
            $table->timestamps();

            $table->foreign('indikator_id')->references('indikator_id')->on('dim_indikator')->cascadeOnDelete();
            $table->unique(['indikator_id', 'kode_kategori'], 'uq_kategori_indikator');
        });

        Schema::create('dim_jenis_kelamin', function (Blueprint $table) {
            $table->smallIncrements('jenis_kelamin_id');
            $table->string('kode_jenis_kelamin', 10)->unique();
            $table->string('nama_jenis_kelamin', 50);
            $table->unsignedSmallInteger('urutan_tampil');
            $table->timestamps();
        });

        Schema::create('dim_sumber_data', function (Blueprint $table) {
            $table->increments('sumber_data_id');
            $table->string('nama_dokumen');
            $table->string('instansi', 200);
            $table->date('tanggal_rilis')->nullable();
            $table->string('versi', 50)->nullable();
            $table->text('lokasi_file')->nullable();
            $table->string('checksum_file', 128)->nullable();
            $table->text('catatan')->nullable();
            $table->timestamps();
        });

        Schema::create('fact_kependudukan', function (Blueprint $table) {
            $table->id('fakta_id');
            $table->unsignedInteger('periode_id');
            $table->unsignedBigInteger('desa_kelurahan_id');
            $table->unsignedInteger('indikator_id');
            $table->unsignedInteger('kategori_id');
            $table->unsignedSmallInteger('jenis_kelamin_id');
            $table->unsignedInteger('sumber_data_id');
            $table->decimal('nilai', 20, 4);
            $table->timestamps();

            $table->foreign('periode_id')->references('periode_id')->on('dim_periode')->cascadeOnDelete();
            $table->foreign('desa_kelurahan_id')->references('desa_kelurahan_id')->on('ref_desa_kelurahan')->cascadeOnDelete();
            $table->foreign('indikator_id')->references('indikator_id')->on('dim_indikator')->cascadeOnDelete();
            $table->foreign('kategori_id')->references('kategori_id')->on('dim_kategori')->cascadeOnDelete();
            $table->foreign('jenis_kelamin_id')->references('jenis_kelamin_id')->on('dim_jenis_kelamin')->restrictOnDelete();
            $table->foreign('sumber_data_id')->references('sumber_data_id')->on('dim_sumber_data')->restrictOnDelete();
            $table->unique([
                'periode_id',
                'desa_kelurahan_id',
                'indikator_id',
                'kategori_id',
                'jenis_kelamin_id',
            ], 'uq_fact_kependudukan');
            $table->index(['periode_id', 'indikator_id']);
            $table->index(['desa_kelurahan_id', 'periode_id']);
            $table->index(['indikator_id', 'kategori_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('fact_kependudukan');
        Schema::dropIfExists('dim_sumber_data');
        Schema::dropIfExists('dim_jenis_kelamin');
        Schema::dropIfExists('dim_kategori');
        Schema::dropIfExists('dim_indikator');
        Schema::dropIfExists('dim_satuan');
        Schema::dropIfExists('dim_kelompok_data');
        Schema::dropIfExists('dim_periode');
        Schema::dropIfExists('ref_desa_kelurahan');
        Schema::dropIfExists('ref_kecamatan');
        Schema::dropIfExists('ref_kabupaten');
        Schema::dropIfExists('ref_provinsi');
    }
};
