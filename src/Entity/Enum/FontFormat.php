<?php

namespace App\Entity\Enum;

enum FontFormat: string
{
    case WOFF2 = 'woff2';
    case WOFF = 'woff';
    case TTF = 'ttf';
}