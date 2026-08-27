<?php

use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\Cache\Adapter\ChainAdapter;
use Symfony\Component\ExpressionLanguage\ExpressionLanguage;

function expressionLanguageCache(ExpressionLanguage $expressionLanguage): ChainAdapter
{
    return new ReflectionProperty($expressionLanguage, 'cache')->getValue($expressionLanguage);
}

function expressionLanguageInProcessAdapter(ChainAdapter $cache): ArrayAdapter
{
    return new ReflectionProperty($cache, 'adapters')->getValue($cache)[0];
}

it('backs the ExpressionLanguage cache with an in-process ArrayAdapter in front of Redis', function () {
    $cache = expressionLanguageCache(app(ExpressionLanguage::class));

    expect(expressionLanguageInProcessAdapter($cache))->toBeInstanceOf(ArrayAdapter::class);
});

it('memoizes a parsed expression in the in-process adapter so a repeat parse skips Redis', function () {
    $expressionLanguage = app(ExpressionLanguage::class);
    $expression = '1 + '.random_int(1, PHP_INT_MAX);

    $expressionLanguage->parse($expression, []);

    $arrayAdapter = expressionLanguageInProcessAdapter(expressionLanguageCache($expressionLanguage));

    expect($arrayAdapter->hasItem(rawurlencode($expression.'//')))->toBeTrue();
});
