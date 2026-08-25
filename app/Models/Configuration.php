<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Configuration extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'configurator_id',
    ];

    public function configurator(): BelongsTo
    {
        return $this->belongsTo(Configurator::class);
    }

    public function answers(): HasMany
    {
        return $this->hasMany(Answer::class);
    }

    public function relevantAnswers(): HasMany
    {
        return $this->hasMany(Answer::class)->whereHas('answerChunk', fn ($query) => $query->where('relevant', true));
    }

    public function answerChunks(): HasMany
    {
        return $this->hasMany(AnswerChunk::class);
    }

    public function relevantAnswerChunks(): HasMany
    {
        return $this->hasMany(AnswerChunk::class)->withAttributes(['relevant' => true])->orderBy('sequence');
    }
}
