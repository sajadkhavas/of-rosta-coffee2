<?php

namespace App\Enums;

enum ContentType: string
{
    case Article = 'article';
    case Guide = 'guide';
    case Comparison = 'comparison';
    case Landing = 'landing';
    case Origin = 'origin';
    case BrewMethod = 'brew_method';
    case Taste = 'taste';
    case Collection = 'collection';
}
