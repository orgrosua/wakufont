<?php

declare(strict_types=1);

namespace App\Command;

use App\Entity\Font;
use Doctrine\Persistence\ManagerRegistry;
use Exception;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\String\Slugger\AsciiSlugger;
use Symfony\Contracts\HttpClient\HttpClientInterface;

#[AsCommand(
    name: 'app:update-fonts',
    description: 'Add a short description for your command',
)]
class UpdateFontsCommand extends Command
{
    private readonly AsciiSlugger $slugger;

    private array $webfont = [];

    public function __construct(
        private readonly HttpClientInterface $client,
        private readonly ManagerRegistry $doctrine,
        private readonly string $webfontApiKey,
    ) {
        parent::__construct();

        $this->slugger = new AsciiSlugger();
    }

    protected function configure(): void
    {
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $entityManager = $this->doctrine->getManager();
        $repository = $this->doctrine->getRepository(Font::class);

        $io = new SymfonyStyle($input, $output);

        $io->info('Fetching fonts from Google Fonts API...');

        $response = $this->client->request('GET', 'https://fonts.google.com/metadata/fonts');

        if ($response->getStatusCode() !== 200) {
            $io->error('Error while fetching fonts from Google Fonts API (metadata)');
            return Command::FAILURE;
        }

        $content = json_decode($response->getContent(), null, 512, JSON_THROW_ON_ERROR);

        $metadataFonts = $content->familyMetadataList;

        try {
            $this->webfont = $this->fetchWebfont();
        } catch (\Throwable $th) {
            $io->error($th->getMessage());
            return Command::FAILURE;
        }

        $io->info('Found ' . (is_countable($metadataFonts) ? count($metadataFonts) : 0) . ' fonts. Updating database...');

        foreach ($metadataFonts as $metadataFont) {
            $io->writeln('Font: ' . $metadataFont->family);

            /** @var Font|null $font */
            $font = $repository->findOneBy([
                'slug' => $this->slugger->slug($metadataFont->family),
            ]);

            if (! $font) {
                $io->writeln('Missing ❌');
                $io->writeln('Creating new one...');
                $font = new Font();
                try {
                    $this->fillFont($font, $metadataFont);
                    $entityManager->persist($font);
                } catch (\Throwable) {
                    //throw $th;
                }
            } else {
                if ($font->getUpdatedAt() < new \DateTimeImmutable($metadataFont->lastModified)) {
                    $io->writeln('Found ⚠️');
                    try {
                        $this->fillFont($font, $metadataFont);
                        // remove cached files
                        $font->getFiles()->clear();
                    } catch (\Throwable) {
                        //throw $th;
                    }
                } else {
                    $io->writeln('Found ✅');
                }
            }

            $io->newLine();
        }

        $entityManager->flush();

        $io->success('Fonts updated.');

        return Command::SUCCESS;
    }

    /**
     * @throws Exception
     */
    private function fetchWebfont(): array
    {
        $items = [];

        $response = $this->client->request('GET', "https://www.googleapis.com/webfonts/v1/webfonts?sort=popularity&key={$this->webfontApiKey}");

        if ($response->getStatusCode() !== 200) {
            throw new Exception('Error while fetching fonts from Google Fonts API (webfont)');
        }

        $content = json_decode($response->getContent(), null, 512, JSON_THROW_ON_ERROR);

        foreach ($content->items as $item) {
            $item->variants = array_map(function ($v) {
                if ($v === 'regular') {
                    return '400';
                } elseif ($v === 'italic') {
                    return '400i';
                } elseif (preg_match('/^(\d+)(italic)$/', $v, $matches)) {
                    return $matches[1] . 'i';
                }

                return $v;
            }, $item->variants);

            $items[$item->family] = $item;
        }

        return $items;
    }

    private function fillFont(Font &$font, $metadataFont)
    {
        $variants = [];

        foreach ($metadataFont->fonts as $k => $v) {
            if (! in_array($k, $this->webfont[$metadataFont->family]->variants, true)) {
                continue;
            }
            $variants[] = $k;
        }

        $font->setAddedAt(new \DateTimeImmutable($metadataFont->dateAdded));
        $font->setAxes($metadataFont->axes);
        $font->setCategory($metadataFont->category);
        $font->setDesigners($metadataFont->designers);
        $font->setDisplayName($metadataFont->displayName ?? null);
        $font->setFamily($metadataFont->family);
        $font->setModifiedAt(new \DateTimeImmutable($metadataFont->lastModified));
        $font->setSlug($this->slugger->slug($metadataFont->family)->toString());
        $font->setSubsets($metadataFont->subsets);
        $font->setVariants($variants);
        $font->setUpdatedAt(new \DateTimeImmutable());
        $font->setPopularity($metadataFont->popularity);
        $font->setVersion($this->webfont[$metadataFont->family]->version);
    }
}
