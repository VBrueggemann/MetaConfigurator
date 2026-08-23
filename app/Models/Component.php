<?php

namespace App\Models;

use App\Enums\ComponentType;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Component extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'configurator_id',
        'type',
        'config',
    ];

    protected $casts = [
        'type' => ComponentType::class,
        'config' => 'array',
    ];

    public function configurator(): BelongsTo
    {
        return $this->belongsTo(Configurator::class);
    }
}
