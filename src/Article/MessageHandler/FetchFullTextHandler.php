<?php

declare(strict_types=1);

namespace App\Article\MessageHandler;

use App\Article\Entity\Article;
use App\Article\Exception\RetryableArticleFetchException;
use App\Article\Message\EnrichArticleMessage;
use App\Article\Message\FetchFullTextMessage;
use App\Article\Repository\ArticleRepositoryInterface;
use App\Article\Service\ArticleContentFetcherServiceInterface;
use App\Article\Service\DomainRateLimiterServiceInterface;
use App\Article\Service\ReadabilityExtractorServiceInterface;
use App\Article\ValueObject\FullTextStatus;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;

#[AsMessageHandler]
final readonly class FetchFullTextHandler
{
    public function __construct(
        private ArticleRepositoryInterface $articleRepository,
        private ArticleContentFetcherServiceInterface $contentFetcher,
        private ReadabilityExtractorServiceInterface $readabilityExtractor,
        private DomainRateLimiterServiceInterface $domainRateLimiter,
        private MessageBusInterface $messageBus,
        private LoggerInterface $logger,
        #[Autowire('%env(bool:FULL_TEXT_FETCH_ENABLED)%')]
        private bool $fullTextEnabled,
    ) {
    }

    public function __invoke(FetchFullTextMessage $message): void
    {
        $correlationId = $message->correlationId;

        $article = $this->articleRepository->findById($message->articleId);

        if (! $article instanceof Article) {
            return;
        }

        if ($article->getFullTextStatus() !== FullTextStatus::Pending) {
            return;
        }

        if (! $this->fullTextEnabled) {
            $this->skipArticle($article, $correlationId);

            return;
        }

        if (! $article->getSource()->isFullTextEnabled()) {
            $this->skipArticle($article, $correlationId);

            return;
        }

        /* Publisher video pages are valid stories but are not text articles. */
        if ($this->isUnsupportedFullTextUrl($article->getUrl())) {
            $this->logger->info('Full-text skipped for unsupported URL {id}', [
                'id' => $article->getId(),
                'url' => $article->getUrl(),
                'correlation_id' => $correlationId,
            ]);

            $this->skipArticle($article, $correlationId);

            return;
        }

        $this->fetchAndExtract($article, $correlationId);
    }

    private function isUnsupportedFullTextUrl(string $url): bool
    {
        $path = strtolower((string) parse_url($url, PHP_URL_PATH));

        return str_contains($path, '/iplayer/') || str_contains($path, '/video/');
    }

    private function skipArticle(Article $article, string $correlationId): void
    {
        $article->setFullTextStatus(FullTextStatus::Skipped);

        $this->articleRepository->flush();

        $this->dispatchEnrich($article, $correlationId);
    }

    private function fetchAndExtract(Article $article, string $correlationId): void
    {
        try {
            $this->domainRateLimiter->waitForDomain($article->getUrl());

            $html = $this->contentFetcher->fetch($article->getUrl());

            if ($article->getImageUrl() === null) {
                $imageUrl = $this->extractPublisherImage($html, $article->getUrl());
                if ($imageUrl !== null) {
                    $article->setImageUrl($imageUrl);
                }
            }

            $result = $this->readabilityExtractor->extract(
                $html,
                $article->getUrl()
            );

            if ($result->success) {
                $article->setContentFullText($result->textContent);
                $article->setContentFullHtml($result->htmlContent);
                $article->setContentText($result->textContent);
                $article->setFullTextStatus(FullTextStatus::Fetched);

                $this->logger->info('Full-text fetched for article {id}', [
                    'id' => $article->getId(),
                    'url' => $article->getUrl(),
                    'correlation_id' => $correlationId,
                ]);
            } else {
                $article->setFullTextStatus(FullTextStatus::Failed);

                $this->logger->warning(
                    'Full-text extraction failed for article {id}',
                    [
                        'id' => $article->getId(),
                        'url' => $article->getUrl(),
                        'correlation_id' => $correlationId,
                    ]
                );
            }
        } catch (RetryableArticleFetchException|TransportExceptionInterface $e) {
            $this->logger->warning('Retryable full-text fetch error for article {id}: {error}', [
                'id' => $article->getId(),
                'url' => $article->getUrl(),
                'error' => $e->getMessage(),
                'correlation_id' => $correlationId,
            ]);

            // Leave the article pending so Messenger's configured retry policy can retry it.
            throw $e;
        } catch (\Throwable $e) {
            $article->setFullTextStatus(FullTextStatus::Failed);

            $this->logger->warning(
                'Full-text fetch error for article {id}: {error}',
                [
                    'id' => $article->getId(),
                    'url' => $article->getUrl(),
                    'error' => $e->getMessage(),
                    'correlation_id' => $correlationId,
                ]
            );
        }

        $this->articleRepository->flush();

        $this->dispatchEnrich($article, $correlationId);
    }

    private function dispatchEnrich(
        Article $article,
        string $correlationId
    ): void {
        $articleId = $article->getId();

        if ($articleId !== null) {
            $this->messageBus->dispatch(
                new EnrichArticleMessage(
                    $articleId,
                    $correlationId
                )
            );
        }
    }

    private function extractPublisherImage(string $html, string $articleUrl): ?string
    {
        if ($html === '' || ! class_exists(\DOMDocument::class)) {
            return null;
        }

        $previous = libxml_use_internal_errors(true);
        try {
            $document = new \DOMDocument();
            if (! $document->loadHTML($html, LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING)) {
                return null;
            }
            $xpath = new \DOMXPath($document);
            $queries = [
                '//meta[translate(@property,"ABCDEFGHIJKLMNOPQRSTUVWXYZ","abcdefghijklmnopqrstuvwxyz")="og:image"]/@content',
                '//meta[translate(@name,"ABCDEFGHIJKLMNOPQRSTUVWXYZ","abcdefghijklmnopqrstuvwxyz")="twitter:image"]/@content',
                '//link[translate(@rel,"ABCDEFGHIJKLMNOPQRSTUVWXYZ","abcdefghijklmnopqrstuvwxyz")="image_src"]/@href',
            ];
            foreach ($queries as $query) {
                $nodes = $xpath->query($query);
                $candidate = $nodes !== false ? trim((string) $nodes->item(0)?->nodeValue) : '';
                $resolved = $this->resolveImageUrl($candidate, $articleUrl);
                if ($resolved !== null) {
                    return $resolved;
                }
            }
        } catch (\Throwable) {
            return null;
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }

        return null;
    }

    private function resolveImageUrl(string $candidate, string $articleUrl): ?string
    {
        if ($candidate === '') {
            return null;
        }
        if (str_starts_with($candidate, '//')) {
            $scheme = (string) parse_url($articleUrl, PHP_URL_SCHEME);
            $candidate = ($scheme !== '' ? $scheme : 'https') . ':' . $candidate;
        } elseif (str_starts_with($candidate, '/')) {
            $parts = parse_url($articleUrl);
            if (! is_array($parts) || ! isset($parts['host'])) {
                return null;
            }
            $scheme = (string) ($parts['scheme'] ?? 'https');
            $port = isset($parts['port']) ? ':' . $parts['port'] : '';
            $candidate = $scheme . '://' . $parts['host'] . $port . $candidate;
        } elseif (! preg_match('~^https?://~i', $candidate)) {
            return null;
        }

        return filter_var($candidate, FILTER_VALIDATE_URL) !== false
            && in_array(strtolower((string) parse_url($candidate, PHP_URL_SCHEME)), ['http', 'https'], true)
            ? $candidate
            : null;
    }
}
