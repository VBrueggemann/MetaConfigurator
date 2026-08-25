<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Answer extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'configuration_id',
        'view_id',
        'answer_chunk_id',
        'value',
    ];

    protected $casts = [
        'value' => 'array',
    ];

    public function configuration(): BelongsTo
    {
        return $this->belongsTo(Configuration::class);
    }

    public function view(): BelongsTo
    {
        return $this->belongsTo(View::class);
    }

    public function answerChunk(): BelongsTo
    {
        return $this->belongsTo(AnswerChunk::class);
    }
}
