<?php

namespace Alexwijn\Changelogs;

use Carbon\Carbon;
use GitWrapper\GitWorkingCopy;
use Illuminate\Support\Collection;
use Illuminate\Support\Fluent;

/**
 * Alexwijn\Changelogs\Changelog
 */
class Changelog
{
    /** @var \GitWrapper\GitWorkingCopy */
    protected $git;

    /**
     * Changelog constructor.
     *
     * @param  \GitWrapper\GitWorkingCopy  $git
     */
    public function __construct(GitWorkingCopy $git)
    {
        $this->git = $git;
    }

    public function generate(string $tag, string $url = null): string
    {
        $url = trim($url, '/');

        $format = '%s [%aN]';
        if (!empty($url)) {
            $format = '%s [%aN] [[%h](' . $url . '/commit/%h)]';
        }


        $commits = explode(
            "\n",
            $this->git->run('log', [$tag, '--pretty="' . $format . '"'])
        );

        $changelog = '';
        $groups = [];
        foreach ($commits as $commit) {
            $result = $this->parse(trim($commit, '"'));
            $groups = array_merge_recursive($groups, $result);
        }

        ksort($groups);

        foreach ($groups as $group => $messages) {
            $changelog .= '### ' . $group . "\n";
            foreach ($messages as $message) {
                $changelog .= "\n* " . ucfirst($message) . "\n";
            }
            $changelog .= "\n";
        }

        return $changelog;
    }

    public function tags(): Collection
    {
        $command = explode(
            "\n",
            rtrim($this->git->run('tag', [
                '--list',
                '--format="%(refname:short)|%(taggerdate)"',
            ]), "\t\n\r\0\x0B")
        );

        $tags = new Collection();
        foreach ($command as $tag) {
            [$name, $date] = explode('|', trim($tag, '"'));
            $tags[] = new Fluent([
                'name' => $name,
                'date' => Carbon::parse($date),
            ]);
        }

        return $tags;
    }

    protected function parse($commit): array
    {
        preg_match('/^[nN]ew\s*:\s*((dev|use?r|pkg|test|doc)\s*:\s*)?([^\n]*)$/', $commit, $new);

        if (isset($new[3])) {
            return ['New' => [$new[3]]];
        }

        preg_match('/^[cC]hg\s*:\s*((dev|use?r|pkg|test|doc)\s*:\s*)?([^\n]*)$/', $commit, $changes);
        if (isset($changes[3])) {
            return ['Changes' => [$changes[3]]];
        }

        preg_match('/^[fF]ix\s*:\s*((dev|use?r|pkg|test|doc)\s*:\s*)?([^\n]*)$/', $commit, $fixes);
        if (isset($fixes[3])) {
            return ['Fixed' => [$fixes[3]]];
        }

        return [];
    }
}
