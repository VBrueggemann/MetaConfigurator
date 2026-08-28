<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class Template extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'key',
        'template',
        'order',
    ];

    public function templatable(): MorphTo
    {
        return $this->morphTo();
    }
}
