<?php

namespace App\Enums;

enum ProductStatus: string
{
    case Draft = 'draft';
    case Review = 'review';
    case Published = 'published';
    case Archived = 'archived';
}
