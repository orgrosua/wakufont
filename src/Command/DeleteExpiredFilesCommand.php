<?php

declare(strict_types=1);

namespace App\Command;

use App\Repository\FileRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:delete-expired-files',
    description: 'Add a short description for your command',
)]
class DeleteExpiredFilesCommand extends Command
{
    public function __construct(
        private readonly ManagerRegistry $doctrine,
        private readonly FileRepository $fileRepository,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $entityManager = $this->doctrine->getManager();
        $io = new SymfonyStyle($input, $output);

        $io->info('Deleting expired Font files...');

        $files = $this->fileRepository->findExpired(new \DateTimeImmutable('-1 day'));

        $io->info('Found ' . count($files) . ' expired files');

        if (count($files) > 0) {
            $io->info('Deleting...');

            $io->progressStart(count($files));

            foreach ($files as $file) {
                $this->fileRepository->remove($file);
                $io->progressAdvance();
            }

            $io->progressFinish();
        }

        $entityManager->flush();

        $io->success('Done');

        return Command::SUCCESS;
    }
}
