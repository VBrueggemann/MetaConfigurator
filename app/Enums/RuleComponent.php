<?php

namespace App\Enums;

enum RuleComponent: string
{
    case Visibility = 'visibility';
    case Validation = 'validation';
    case NextGroup = 'next_group';
}
