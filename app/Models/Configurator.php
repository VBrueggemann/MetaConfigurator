<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Str;

class Configurator extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'name',
        'slug',
        'scope_depth',
        'graphs_acyclic',
    ];

    protected $casts = [
        'graphs_acyclic' => 'bool',
    ];

    protected static function booted(): void
    {
        static::creating(function (Configurator $configurator): void {
            $configurator->slug ??= static::uniqueSlugFor($configurator->name);
        });
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    private static function uniqueSlugFor(string $name): string
    {
        $base = Str::slug($name);
        $slug = $base;

        for ($suffix = 2; static::query()->where('slug', $slug)->exists(); $suffix++) {
            $slug = "{$base}-{$suffix}";
        }

        return $slug;
    }

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

    public function calculations(): HasMany
    {
        return $this->hasMany(Calculation::class);
    }
}
