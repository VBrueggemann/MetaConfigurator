<?php

namespace App\Enums;

enum ComponentType: string
{
    case Visibility = 'visibility';
    case Validation = 'validation';
    case Calculation = 'calculation';
    case Scope = 'scope';
    case Views = 'views';
    case Breadcrumbs = 'breadcrumbs';
    case PendingViews = 'pending_views';
}
