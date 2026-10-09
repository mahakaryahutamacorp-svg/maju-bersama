<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AssistantInquiry extends Model
{
    protected $fillable = [
        'user_id',
        'branch_id',
        'question',
        'outcome',
        'tools_used',
    ];

    protected function casts(): array
    {
        return [
            'tools_used' => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }
}
