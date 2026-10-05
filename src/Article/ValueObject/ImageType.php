<?php

declare(strict_types=1);

namespace App\Article\ValueObject;

enum ImageType: string
{
    case Source = 'SOURCE';
    case Provider = 'PROVIDER';
    case LicensedStock = 'LICENSED_STOCK';
    case GeneratedIllustration = 'GENERATED_ILLUSTRATION';
    case Placeholder = 'PLACEHOLDER';
}
