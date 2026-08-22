<?php

namespace App\Enums;

enum ViewType: string
{
    case Group = 'group';
    case Select = 'select';
    case TextInput = 'text_input';
    case Html = 'html';
}
