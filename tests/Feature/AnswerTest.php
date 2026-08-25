<?php

use App\Models\Answer;

it('persists a null answer value', function () {
    $answer = Answer::factory()->create(['value' => null]);

    expect($answer->fresh()->value)->toBeNull();
});
