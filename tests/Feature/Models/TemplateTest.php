<?php

use App\Models\Rule;
use App\Models\Template;
use App\Models\View;

it('belongs to its templatable View via a polymorphic relation', function () {
    $view = View::factory()->create();
    $template = $view->templates()->create(['key' => 'label', 'template' => 'Hello']);

    expect($template->templatable)->toBeInstanceOf(View::class);
    expect($template->templatable->id)->toBe($view->id);
});

it('belongs to its templatable Rule via a polymorphic relation', function () {
    $rule = Rule::factory()->create();
    $template = $rule->templates()->create(['key' => 'message', 'template' => 'Required']);

    expect($template->templatable)->toBeInstanceOf(Rule::class);
    expect($template->templatable->id)->toBe($rule->id);
});

it('is retrievable from the owning View via templates()', function () {
    $view = View::factory()->create();
    Template::factory()->for($view, 'templatable')->create(['key' => 'label', 'template' => 'Name']);
    Template::factory()->for($view, 'templatable')->create(['key' => 'content', 'template' => 'Body']);

    expect($view->templates()->pluck('key')->sort()->values()->all())->toBe(['content', 'label']);
});
