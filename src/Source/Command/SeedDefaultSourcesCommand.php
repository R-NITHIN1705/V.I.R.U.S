<?php

declare(strict_types=1);

namespace App\Source\Command;

use App\Shared\Repository\CategoryRepositoryInterface;
use App\Source\Entity\Source;
use App\Source\Repository\SourceRepositoryInterface;
use Psr\Clock\ClockInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'app:seed-default-sources', description: 'Add verified Indian, international and Telangana news feeds if missing.')]
final class SeedDefaultSourcesCommand extends Command
{
    public function __construct(
        private readonly CategoryRepositoryInterface $categories,
        private readonly SourceRepositoryInterface $sources,
        private readonly ClockInterface $clock
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $politics = $this->categories->findBySlug('politics');
        if ($politics === null) {
            $io->error('Run app:seed-data first to create the standard categories.');
            return Command::FAILURE;
        }
        $definitions = [
            ['The Indian Express — India', 'https://indianexpress.com/section/india/feed/', 'https://indianexpress.com', 'national', 'IN'],
            ['Hindustan Times — India', 'https://www.hindustantimes.com/feeds/rss/india-news/rssfeed.xml', 'https://www.hindustantimes.com', 'national', 'IN'],
            ['India Today — National', 'https://www.indiatoday.in/rss/1206584', 'https://www.indiatoday.in', 'national', 'IN'],
            ['Times of India — Top Stories', 'https://timesofindia.indiatimes.com/rssfeedstopstories.cms', 'https://timesofindia.indiatimes.com', 'national', 'IN'],
            ['NDTV — Top Stories', 'https://feeds.feedburner.com/ndtvnews-top-stories', 'https://www.ndtv.com', 'national', 'IN'],
            ['BBC — World', 'https://feeds.bbci.co.uk/news/world/rss.xml', 'https://www.bbc.com/news', 'international', 'GB'],
            ['Al Jazeera — All News', 'https://www.aljazeera.com/xml/rss/all.xml', 'https://www.aljazeera.com', 'international', 'QA'],
            ['Telangana Today', 'https://telanganatoday.com/feed', 'https://telanganatoday.com', 'state', 'IN'],
            ['The Indian Express — Hyderabad', 'https://indianexpress.com/section/cities/hyderabad/feed/', 'https://indianexpress.com', 'state', 'IN'],
            ['Times of India — Hyderabad', 'https://timesofindia.indiatimes.com/rssfeeds/-2128816011.cms', 'https://timesofindia.indiatimes.com', 'state', 'IN'],
        ];
        $added = 0;
        foreach ($definitions as [$name,$url,$site,$region,$country]) {
            $source = $this->sources->findByFeedUrl($url);
            if ($source === null) {
                $source = new Source($name, $url, $politics, $this->clock->now());
                $source->setSiteUrl($site);
                $source->setLanguage('en');
                $source->setFetchIntervalMinutes(3);
                $source->setCountry($country);
                $source->setReliabilityWeight(0.85);
                $source->setEnabled(true);
                $this->sources->save($source);
                $added++;
            }
            if ($source->getRegion() === null) {
                $source->setRegion($region);
            }
            if ($source->getCountry() === null) {
                $source->setCountry($country);
            }
            $source->setDefaultSource(true);
        }
        $this->sources->flush();
        $io->success(sprintf('%d default sources added; existing feed URLs were retained.', $added));
        return Command::SUCCESS;
    }
}
