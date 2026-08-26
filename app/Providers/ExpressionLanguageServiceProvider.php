<?php

namespace App\Providers;

use App\Actions\Pipeline\ResolveModelFunction;
use Illuminate\Support\ServiceProvider;
use Symfony\Component\ExpressionLanguage\ExpressionLanguage;

class ExpressionLanguageServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        $this->app->singleton(ExpressionLanguage::class, function () {
            $expressionLanguage = new ExpressionLanguage;

            $expressionLanguage->register(
                'strlen',
                fn ($value) => sprintf('strlen((string) (%s))', $value),
                fn (array $arguments, $value) => strlen((string) $value),
            );

            $expressionLanguage->register(
                'count',
                fn ($value) => sprintf('count((array) (%s))', $value),
                fn (array $arguments, $value) => count((array) $value),
            );

            foreach (config('templating.functions', []) as $modelClass => $functions) {
                foreach (array_keys($functions) as $name) {
                    $expressionLanguage->register(
                        $name,
                        fn ($id) => sprintf('%s(%s)', $name, $id),
                        fn (array $arguments, $id) => app(ResolveModelFunction::class)($arguments, $modelClass, (string) $id, $name),
                    );
                }
            }

            return $expressionLanguage;
        });
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        //
    }
}
