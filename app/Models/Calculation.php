<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Calculation extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'configurator_id',
        'name',
        'expression',
    ];

    public function configurator(): BelongsTo
    {
        return $this->belongsTo(Configurator::class);
    }
}
