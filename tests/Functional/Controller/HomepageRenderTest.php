<?php

declare(strict_types=1);

namespace App\Tests\Functional\Controller;

use App\User\Entity\User;
use App\Article\Entity\Article;
use App\Shared\Entity\Category;
use App\Source\Entity\Source;
use App\User\Entity\UserArticleBookmark;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\CoversNothing;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

#[CoversNothing]
final class HomepageRenderTest extends WebTestCase
{
    public function testAuthenticatedHomepageRendersEditorialLayoutAndExistingNavigation(): void
    {
        $client = self::createClient();
        $user = new User('homepage-'.bin2hex(random_bytes(5)).'@example.test', 'test-hash');
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $entityManager->persist($user);
        $entityManager->flush();
        $client->loginUser($user);

        $client->request('GET', '/');

        self::assertResponseIsSuccessful();
        self::assertSelectorExists('.virus-header');
        self::assertSelectorExists('.virus-globe');
        self::assertSelectorTextContains('.virus-brand', 'V.I.R.U.S.');
        self::assertSelectorTextContains('#feed-overview-title', 'EXPLORE THE WORLD');
        self::assertSelectorExists('a[href="/explore"]');
        self::assertSelectorTextContains('.virus-orbit-caption', 'story locations are not available');
        self::assertSelectorTextContains('#latest-title', 'Latest news');
        self::assertSelectorExists('a[href="/sources"]');
        self::assertSelectorExists('a[href="/alerts"]');
        self::assertSelectorExists('a[href="/digests"]');
        self::assertSelectorExists('a[href="/notifications"]');
        self::assertSelectorExists('a[href="/chat"]');
        self::assertSelectorNotExists('a[href="/stats/ai"]');
        self::assertSelectorExists('a[href="/settings"]');

        $client->request('GET', '/explore');
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'What do you want');
        self::assertSelectorExists('form[action="/search"]');

        $client->request('GET', '/search');
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', "Search the world's news");
        self::assertSelectorExists('input[name="q"]');
        self::assertSelectorExists('select[name="period"]');
        self::assertSelectorExists('input[name="country"]');
        self::assertSelectorExists('select[name="language"]');
        self::assertSelectorExists('select[name="category"]');
        self::assertSelectorExists('select[name="source"]');

        $client->request('GET', '/settings');
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'Settings');

        $client->request('GET', '/sources');
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'Sources');

        $client->request('GET', '/saved');
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'Saved stories');
    }

    public function testRealSourceAndSavedStoryCardsRender(): void
    {
        $client = self::createClient();
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $user = new User('saved-'.bin2hex(random_bytes(5)).'@example.test', 'test-hash');
        $category = new Category('Technology', 'technology-'.bin2hex(random_bytes(3)), 7, '#3B82F6');
        $fixtureId = bin2hex(random_bytes(6));
        $source = new Source('Example Daily', 'https://example.test/'.$fixtureId.'/feed.xml', $category, new \DateTimeImmutable());
        $source->setRegion('national');
        $article = new Article('A real test story', 'https://example.test/'.$fixtureId.'/story', $source, new \DateTimeImmutable());
        $bookmark = new UserArticleBookmark($user, $article, new \DateTimeImmutable());
        foreach ([$user, $category, $source, $article, $bookmark] as $entity) $entityManager->persist($entity);
        $entityManager->flush();
        $client->loginUser($user);

        $client->request('GET', '/sources');
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('[data-source-card]', 'Example Daily');
        self::assertSelectorTextContains('[data-source-card]', '1 stories');

        $client->request('GET', '/saved');
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('.saved-card', 'A real test story');
    }
}
