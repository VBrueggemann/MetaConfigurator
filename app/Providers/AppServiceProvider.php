<?php

namespace App\Providers;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;
use Staudenmeir\LaravelAdjacencyList\Eloquent\Collection as AdjacencyListCollection;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();
        $this->configureAdjacencyListCollection();
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        Password::defaults(fn (): ?Password => app()->isProduction()
            ? Password::min(12)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols()
                ->uncompromised()
            : null,
        );

        if (! app()->isProduction()) {
            Model::shouldBeStrict();
        }
    }

    protected function configureAdjacencyListCollection(): void
    {
        AdjacencyListCollection::macro('toList', function (string $childrenRelation = 'children'): AdjacencyListCollection {
            return $this->reduce(
                fn (AdjacencyListCollection $list, Model $item): AdjacencyListCollection => $list
                    ->push($item)
                    ->merge($item->getRelation($childrenRelation)->toList($childrenRelation)),
                new AdjacencyListCollection
            );
        });
    }
}
