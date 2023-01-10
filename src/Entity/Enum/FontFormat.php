<?php

declare(strict_types=1);

namespace App\Entity\Enum;

enum FontFormat: string
{
    case WOFF2 = 'woff2';
    case WOFF = 'woff';
    case TTF = 'ttf';
}
