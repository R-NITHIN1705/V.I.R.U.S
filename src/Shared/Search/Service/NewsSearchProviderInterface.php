<?php

declare(strict_types=1);

namespace App\Shared\Search\Service;

use App\Shared\Search\ValueObject\NewsSearchProviderResponse;
use App\Shared\Search\ValueObject\NewsSearchQuery;

interface NewsSearchProviderInterface
{
    public function search(NewsSearchQuery $query): NewsSearchProviderResponse;
}
