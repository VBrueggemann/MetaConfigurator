<?php

use App\Actions\DTO\ConfigurationEvaluation;
use App\Enums\ViewType;
use App\Models\Answer;
use App\Models\AnswerChunk;
use App\Models\Configuration;
use App\Models\View;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "pest()" function to bind different classes or traits.
|
*/

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

function answer(Configuration $configuration, View $view, mixed $value): ?Answer
{
    $chunk = AnswerChunk::factory()->create([
        'configuration_id' => $configuration->id,
        'anchor_view_id' => $view->id,
        'sequence' => $configuration->answerChunks()->max('sequence') + 1,
    ]);

    if ($view->type !== ViewType::Input) {
        return null;
    }

    return Answer::factory()->create([
        'configuration_id' => $configuration->id,
        'view_id' => $view->id,
        'answer_chunk_id' => $chunk->id,
        'value' => $value,
    ]);
}

/**
 * @return list<string>
 */
function scopeIds(ConfigurationEvaluation $evaluation): array
{
    $flatten = function ($views) use (&$flatten): array {
        return collect($views)->flatMap(fn ($view) => [$view->id, ...$flatten($view->children)])->all();
    };

    return $flatten($evaluation->componentContexts['scope']);
}
