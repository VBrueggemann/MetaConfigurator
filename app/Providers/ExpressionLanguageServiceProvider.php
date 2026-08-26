<?php

namespace App\Providers;

use App\Actions\Pipeline\ResolveTemplateField;
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

            $expressionLanguage->register(
                'label',
                fn ($id) => sprintf('label(%s)', $id),
                fn (array $arguments, $id) => app(ResolveTemplateField::class)($arguments, 'views', (string) $id, 'label'),
            );

            $expressionLanguage->register(
                'message',
                fn ($id) => sprintf('message(%s)', $id),
                fn (array $arguments, $id) => app(ResolveTemplateField::class)($arguments, 'rules', (string) $id, 'message'),
            );

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
