<?php

namespace App\Models;

use App\Enums\RuleComponent;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class Rule extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'view_id',
        'component',
        'expression',
        'meta',
        'order',
    ];

    protected $casts = [
        'component' => RuleComponent::class,
        'meta' => 'array',
    ];

    public function view(): BelongsTo
    {
        return $this->belongsTo(View::class);
    }

    public function templates(): MorphMany
    {
        return $this->morphMany(Template::class, 'templatable');
    }
}
