<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

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

    public function rootView(): HasOne
    {
        return $this->hasOne(View::class)->withAttributes(['parent_id' => null]);
    }

    public function configurations(): HasMany
    {
        return $this->hasMany(Configuration::class);
    }

    public function components(): HasMany
    {
        return $this->hasMany(Component::class);
    }
}
