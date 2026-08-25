<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AnswerChunk extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'configuration_id',
        'anchor_view_id',
        'relevant',
        'sequence',
    ];

    protected $casts = [
        'relevant' => 'boolean',
    ];

    public function configuration(): BelongsTo
    {
        return $this->belongsTo(Configuration::class);
    }

    public function anchorView(): BelongsTo
    {
        return $this->belongsTo(View::class, 'anchor_view_id');
    }

    public function answers(): HasMany
    {
        return $this->hasMany(Answer::class);
    }
}
