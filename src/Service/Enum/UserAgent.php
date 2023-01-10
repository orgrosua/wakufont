<?php

declare(strict_types=1);

namespace App\Service\Enum;

enum UserAgent: string
{
    case WOFF2 = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:43.0) Gecko/20100101 Firefox/43.0';
    case WOFF = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:38.0) Gecko/20100101 Firefox/38.0';
    case TTF = 'Mozilla/5.0 (Unknown; Linux x86_64) AppleWebKit/538.1 (KHTML, like Gecko) Safari/538.1 Daum/4.1';
    case CURRENT = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:109.0) Gecko/20100101 Firefox/109.0';
}
