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
    ];

    protected $casts = [
        'is_scanned_pdf' => 'boolean',
        'size_bytes' => 'integer',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
