<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PekerjaanVendor extends Model
{
    protected $table = 'pekerjaan_vendor';
    protected $fillable = ['pekerjaan_id', 'perusahaan_id'];

    public function pekerjaan()
    {
        return $this->belongsTo(Pekerjaan::class);
    }

    public function perusahaan()
    {
        return $this->belongsTo(\App\Models\Master\Perusahaan::class);
    }
}
