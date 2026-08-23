<?php

namespace App\Models;

use App\Enums\ViewType;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Staudenmeir\LaravelAdjacencyList\Eloquent\HasRecursiveRelationships;

class View extends Model
{
    use HasFactory, HasRecursiveRelationships, HasUuids;

    protected $fillable = [
        'configurator_id',
        'parent_id',
        'type',
        'order',
        'config',
    ];

    protected $casts = [
        'type' => ViewType::class,
        'config' => 'array',
    ];

    public function configurator(): BelongsTo
    {
        return $this->belongsTo(Configurator::class);
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(View::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(View::class, 'parent_id')->orderBy('order');
    }

    public function rules(): HasMany
    {
        return $this->hasMany(Rule::class);
    }

    public function answers(): HasMany
    {
        return $this->hasMany(Answer::class);
    }
}
