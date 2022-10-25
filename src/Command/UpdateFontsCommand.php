<?php

namespace App\Command;

use App\Entity\Font;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
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
    private AsciiSlugger $slugger;

    public function __construct(
        private HttpClientInterface $client,
        private ManagerRegistry $doctrine
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
            $io->error('Error while fetching fonts');
            return Command::FAILURE;
        }

        $content = json_decode($response->getContent());

        $metadataFonts = $content->familyMetadataList;

        $io->info('Found ' . count($metadataFonts) . ' fonts. Updating database...');

        foreach ($metadataFonts as $metadataFont) {
            $io->writeln('Font: ' . $metadataFont->family);

            /** @var Font|null $font */
            $font = $repository->findOneBy(['slug' => $this->slugger->slug($metadataFont->family)]);

            if (!$font) {
                $io->writeln('Missing ❌');
                $io->writeln('Creating new one...');
                $font = new Font();
                $this->fillFont($font, $metadataFont);
                $entityManager->persist($font);
            } else {
                if ($font->getUpdatedAt() < new \DateTimeImmutable($metadataFont->lastModified)) {
                    $io->writeln('Found ⚠️');
                    $this->fillFont($font, $metadataFont);
                    // remove cached files
                    $font->getFiles()->clear();
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

    private function fillFont(Font &$font, $metadataFont)
    {
        $variants = [];

        foreach ($metadataFont->fonts as $k => $v) {
            $variants[] = $k;
        }

        $font->setAddedAt(new \DateTimeImmutable($metadataFont->dateAdded));
        $font->setAxes($metadataFont->axes);
        $font->setCategory($metadataFont->category);
        $font->setDesigners($metadataFont->designers);
        $font->setDisplayName($metadataFont->displayName ?? null);
        $font->setFamily($metadataFont->family);
        $font->setModifiedAt(new \DateTimeImmutable($metadataFont->lastModified));
        $font->setSlug($this->slugger->slug($metadataFont->family));
        $font->setSubsets($metadataFont->subsets);
        $font->setVariants($variants);
        $font->setUpdatedAt(new \DateTimeImmutable());
        $font->setPopularity($metadataFont->popularity);
    }
}
