<?php

declare(strict_types=1);

namespace App\Tests\E2E;

use App\Article\Entity\Article;
use App\Shared\Entity\Category;
use App\Source\Entity\Source;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\CoversNothing;
use Symfony\Component\Panther\PantherTestCase;

#[CoversNothing]
final class ArticleImageProvenanceE2ETest extends PantherTestCase
{
    use CreatesPantherUser;

    public function testMissingPublisherImageFallsBackWithHonestAttribution(): void
    {
        $credentials = $this->createPantherUser();
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $suffix = bin2hex(random_bytes(5));
        $category = new Category('Technology', 'technology-' . $suffix, 7, '#3B82F6');
        $source = new Source('Example Publisher ' . $suffix, 'https://example.test/' . $suffix . '/feed.xml', $category, new \DateTimeImmutable());
        $article = new Article('A test technology story ' . $suffix, 'https://example.test/' . $suffix . '/story', $source, new \DateTimeImmutable());
        $article->setCategory($category);
        $article->setImageUrl('https://missing-image.example.invalid/' . $suffix . '.jpg');
        foreach ([$category, $source, $article] as $entity) {
            $entityManager->persist($entity);
        }
        $entityManager->flush();

        $client = self::createPantherClient();
        $client->request('GET', '/login');
        $client->submitForm('Sign In', [
            '_username' => $credentials['email'],
            '_password' => $credentials['password'],
        ]);

        $client->request('GET', '/stories/' . $article->getId());
        self::assertSelectorExists('.story-hero-image img');
        $client->getWebDriver()->wait(8, 100)->until(static fn ($driver): bool => $driver->executeScript("return document.querySelector('.story-hero-image img').dataset.imageType") !== 'SOURCE');
        self::assertSame('GENERATED_ILLUSTRATION', $client->executeScript("return document.querySelector('.story-hero-image img').dataset.imageType"));
        self::assertSame('Editorial illustration representing Technology', $client->executeScript("return document.querySelector('.story-hero-image img').alt"));
        self::assertSelectorTextContains('.story-hero-image [data-image-provenance]', 'AI-generated illustration');
        self::assertStringContainsString('/images/editorial/tech.jpg', (string) $client->executeScript("return document.querySelector('.story-hero-image img').getAttribute('src')"));

        $client->executeScript("document.querySelector('.story-hero-image img').dispatchEvent(new Event('error'))");
        self::assertSame('PLACEHOLDER', $client->executeScript("return document.querySelector('.story-hero-image img').dataset.imageType"));
        self::assertSelectorTextContains('.story-hero-image [data-image-provenance]', 'Image unavailable');
        self::assertStringNotContainsString('V.I.R.U.S.', (string) $client->executeScript("return document.querySelector('.story-hero-image').textContent"));

        $client->executeScript("document.querySelector('[data-mode-toggle]').click()");
        self::assertSame('light', $client->executeScript('return document.documentElement.dataset.mode'));
        self::assertSelectorExists('.story-hero-image [data-image-provenance]');
        $client->executeScript("document.querySelector('[data-mode-toggle]').click()");
        self::assertSame('dark', $client->executeScript('return document.documentElement.dataset.mode'));
    }
}
