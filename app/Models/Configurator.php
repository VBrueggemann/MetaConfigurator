<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Configurator extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'name',
    ];

    public function views(): HasMany
    {
        return $this->hasMany(View::class);
    }

    public function groups(): HasMany
    {
        return $this->views()->whereNull('parent_id')->orderBy('order');
    }

    public function configurations(): HasMany
    {
        return $this->hasMany(Configuration::class);
    }
}
