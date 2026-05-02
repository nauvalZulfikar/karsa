<?php

namespace App\Models;

use App\Models\Master\TenagaAhli;
use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class PekerjaanPersonil extends Model
{
    use LogsActivity;

    protected $table = 'pekerjaan_personil';

    protected $fillable = [
        'pekerjaan_id',
        'tenaga_ahli_id',
        'jabatan_kontrak',
        'nilai_honor_kontrak',
        'tanggal_mulai_tugas',
        'tanggal_akhir_tugas',
        'is_active',
    ];

    protected $casts = [
        'tanggal_mulai_tugas' => 'date',
        'tanggal_akhir_tugas' => 'date',
        'nilai_honor_kontrak' => 'decimal:2',
        'is_active' => 'boolean',
    ];

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logFillable()->logOnlyDirty();
    }

    public function pekerjaan()
    {
        return $this->belongsTo(Pekerjaan::class);
    }

    public function tenagaAhli()
    {
        return $this->belongsTo(TenagaAhli::class);
    }
}
