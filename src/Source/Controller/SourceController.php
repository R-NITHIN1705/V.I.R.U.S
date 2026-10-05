<?php

declare(strict_types=1);

namespace App\Source\Controller;

use App\Source\Repository\SourceRepositoryInterface;
use Symfony\Bundle\FrameworkBundle\Controller\ControllerHelper;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Doctrine\ORM\EntityManagerInterface;

final class SourceController
{
    public function __construct(
        private readonly ControllerHelper $controller,
        private readonly SourceRepositoryInterface $sourceRepository,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    #[Route('/sources', name: 'app_sources')]
    public function __invoke(): Response
    {
        $sources = $this->sourceRepository->findAll();
        $counts = [];
        if ($sources !== []) {
            $ids = array_values(array_filter(array_map(static fn ($s) => $s->getId(), $sources)));
            if ($ids !== []) {
                $rows = $this->entityManager->getConnection()->executeQuery('SELECT source_id, COUNT(*) AS total FROM article WHERE source_id IN (?) GROUP BY source_id', [$ids], [\Doctrine\DBAL\ArrayParameterType::INTEGER])->fetchAllKeyValue();
                foreach ($rows as $id => $total) $counts[(int) $id] = (int) $total;
            }
        }
        $categoryNames = array_values(array_unique(array_map(static fn ($source) => $source->getCategory()->getName(), $sources)));
        sort($categoryNames);
        return $this->controller->render('source/index.html.twig', ['sources' => $sources, 'articleCounts' => $counts, 'sourceCategories' => $categoryNames]);
    }
}
