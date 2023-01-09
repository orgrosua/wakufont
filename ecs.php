<?php

declare(strict_types=1);

use Symplify\EasyCodingStandard\Config\ECSConfig;
use Symplify\EasyCodingStandard\ValueObject\Set\SetList;

return static function (ECSConfig $ecsConfig): void {
    $ecsConfig->paths([
        __DIR__ . '/src',
        // __DIR__ . '/tests'
    ]);

    // A. full sets
    $ecsConfig->sets([
        // SetList::CLEAN_CODE,
        // SetList::COMMON,
        SetList::PSR_12
    ]);

};
