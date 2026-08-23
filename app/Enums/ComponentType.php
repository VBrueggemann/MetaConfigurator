<?php

namespace App\Enums;

enum ComponentType: string
{
    case Visibility = 'visibility';
    case Validation = 'validation';
    case Calculation = 'calculation';
    case Scope = 'scope';
}
