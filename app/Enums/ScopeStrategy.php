<?php

namespace App\Enums;

enum ScopeStrategy: string
{
    case All = 'all';
    case JustNext = 'just_next';
    case AnsweredPlusN = 'answered_plus_n';
}
