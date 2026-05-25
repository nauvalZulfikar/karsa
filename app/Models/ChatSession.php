<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ChatSession extends Model
{
    protected $fillable = ['user_id', 'title', 'messages', 'ai_messages'];

    protected $casts = [
        'messages' => 'array',
        'ai_messages' => 'array',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
