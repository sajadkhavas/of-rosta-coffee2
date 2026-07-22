<?php

namespace App\Enums;

enum ProcessingMethod: string
{
    case Washed = 'washed';
    case Natural = 'natural';
    case Honey = 'honey';
    case Other = 'other';
}
