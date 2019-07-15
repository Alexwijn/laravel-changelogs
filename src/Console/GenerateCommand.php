<?php

namespace Alexwijn\Changelogs\Console;

use Alexwijn\Changelogs\Changelog;
use GitWrapper\GitWrapper;
use Illuminate\Support\Fluent;
use Symfony\Component\Console\Command\Command as SymfonyCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Alexwijn\Changelogs\Console\GenerateCommand
 */
class GenerateCommand extends SymfonyCommand
{
    protected $name = 'generate';

    public function execute(InputInterface $input, OutputInterface $output): void
    {
        if (!is_dir('logs') && !mkdir('logs')) {
            throw new \RuntimeException(sprintf('Directory "%s" was not created', 'logs'));
        }

        $workingDirectory = (new GitWrapper())->workingCopy(getcwd());
        $changelog = new Changelog($workingDirectory);
        $url = $input->getArgument('url');

        $logs = [];
        $tags = $changelog->tags();
        if ($tags->isEmpty()) {
            return;
        }

        foreach ($changelog->tags() as $key => $tag) {
            $file = 'logs/CHANGELOG-' . $tag->name . '.md';
            if (!$input->getOption('force') && file_exists($file)) {
                $logs[] = file_get_contents($file);
                continue;
            }

            $revisions = $tag->name;
            if (isset($tags[$key - 1])) {
                $revisions = $changelog->tags()->get($key - 1)->name . '...' . $tag->name;
            }

            $output->writeln('Generating logs for ' . $tag->name . '...');
            $log = '# ' . $tag->name . ' (' . $tag->date->format('d-m-Y') . ')' . "\n\n";
            $log .= $changelog->generate($revisions, $url);

            file_put_contents($file, $logs[] = $log);
        }

        if (!isset($tag)) {
            $tag = new Fluent(['name' => '']);
        }

        $output->writeln('Generating logs for UNRELEASED...');
        $unreleased = '# UNRELEASED ' . "\n\n";
        $unreleased .= $changelog->generate(trim($tag->name . '...HEAD', '.'), $url);

        file_put_contents('CHANGELOG.md', $unreleased . implode("\n", array_reverse($logs)));
    }

    /**
     * Configure the command options.
     *
     * @return void
     */
    protected function configure(): void
    {
        $this
            ->setName('generate')
            ->setDescription('Generate the changelogs.')
            ->addArgument('url', null, 'Set the url for visiting the commit')
            ->addOption('force', 'f', null, 'Overwrite any existing changelogs');
    }
}
