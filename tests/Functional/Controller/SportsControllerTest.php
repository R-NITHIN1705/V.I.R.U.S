<?php

declare(strict_types=1);

namespace App\Tests\Functional\Controller;

use App\Article\Entity\Article;
use App\Shared\Entity\Category;
use App\Source\Entity\Source;
use App\User\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\CoversNothing;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

#[CoversNothing]
final class SportsControllerTest extends WebTestCase
{
    public function testExploreSportsButtonOpensSportsPageAndShowsActualSportsNews(): void
    {
        $client = self::createClient();
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $user = new User('sports-' . bin2hex(random_bytes(5)) . '@example.test', 'test-hash');
        $category = $entityManager->getRepository(Category::class)->findOneBy([
            'slug' => 'sports',
        ]);
        if (! $category instanceof Category) {
            $category = new Category('Sports', 'sports', 6, '#8B5CF6');
            $entityManager->persist($category);
        }
        $fixtureId = bin2hex(random_bytes(6));
        $source = new Source('Sports Desk ' . $fixtureId, 'https://example.test/' . $fixtureId . '/sports-feed.xml', $category, new \DateTimeImmutable());
        $article = new Article('Verified sports story ' . $fixtureId, 'https://example.test/' . $fixtureId . '/sports-story', $source, new \DateTimeImmutable());
        $article->setCategory($category);
        foreach ([$user, $source, $article] as $entity) {
            $entityManager->persist($entity);
        }
        $entityManager->flush();
        $client->loginUser($user);

        $client->request('GET', '/explore');
        self::assertResponseIsSuccessful();
        self::assertSelectorExists('.explore-topic[href="/sports"]');

        $client->request('GET', '/sports');
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'Sports');
        self::assertSelectorTextContains('[role="status"]', 'Sports data is not configured yet');
        self::assertSelectorTextContains('.virus-sports-news', 'Verified sports story ' . $fixtureId);
        self::assertSelectorExists('.virus-sports-news img[alt="Editorial illustration representing Sports"]');
        self::assertSelectorTextContains('.virus-sports-news [data-image-provenance]', 'AI-generated illustration');

        $client->request('GET', '/stories/' . $article->getId());
        self::assertResponseIsSuccessful();
        self::assertSelectorExists('.story-hero-image img[alt="Editorial illustration representing Sports"]');
        self::assertSelectorTextContains('.story-hero-image [data-image-provenance]', 'AI-generated illustration');
    }
}
