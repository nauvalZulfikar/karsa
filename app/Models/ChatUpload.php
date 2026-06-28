<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ChatUpload extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'original_name',
        'abs_path',
        'mime',
        'size_bytes',
        'is_scanned_pdf',
        'ocr_text',
        'organized_at',
        'dokumen_id',
    ];

    protected $casts = [
        'is_scanned_pdf' => 'boolean',
        'size_bytes' => 'integer',
        'organized_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function dokumen(): BelongsTo
    {
        return $this->belongsTo(Dokumen::class);
    }

    /** File yang belum dipindah ke library Dokumen. */
    public function scopeUnorganized($query)
    {
        return $query->whereNull('organized_at');
    }

    /** Kategori render untuk viewer: pdf | image | sheet | word | other. */
    public function kind(): string
    {
        $ext = strtolower(pathinfo((string) $this->original_name, PATHINFO_EXTENSION));

        return match (true) {
            $ext === 'pdf' || str_contains((string) $this->mime, 'pdf') => 'pdf',
            in_array($ext, ['jpg', 'jpeg', 'png', 'webp', 'gif']) => 'image',
            in_array($ext, ['xls', 'xlsx', 'csv']) => 'sheet',
            in_array($ext, ['doc', 'docx']) => 'word',
            default => 'other',
        };
    }
}
