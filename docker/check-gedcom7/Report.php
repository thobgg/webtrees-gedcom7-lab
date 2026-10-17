<?php

declare(strict_types=1);

namespace Webtrees7Check;

use function array_slice;
use function count;
use function str_pad;

use const PHP_EOL;

/**
 * The results of the checks, printed as they happen.
 */
final class Report
{
    private const int EXAMPLES = 10;

    private int $passed = 0;

    /** @var list<string> */
    private array $failed = [];

    private string $section = '';

    public function section(string $title): void
    {
        $this->section = $title;

        echo PHP_EOL, '== ', $title, ' ==', PHP_EOL;
    }

    /**
     * @param list<string> $errors
     */
    public function check(string $label, array $errors): bool
    {
        if ($errors === []) {
            $this->passed++;
            echo '  ok    ', $label, PHP_EOL;

            return true;
        }

        $this->failed[] = $this->section . ': ' . $label . ' (' . count($errors) . ')';

        echo '  FAIL  ', $label, ' - ', count($errors), ' problem', count($errors) === 1 ? '' : 's', PHP_EOL;

        foreach (array_slice($errors, 0, self::EXAMPLES) as $error) {
            echo '          ', $error, PHP_EOL;
        }

        if (count($errors) > self::EXAMPLES) {
            echo '          ...', PHP_EOL;
        }

        return false;
    }

    public function info(string $label, string $text): void
    {
        echo '  info  ', str_pad($label, 20), ' ', $text, PHP_EOL;
    }

    public function summary(): int
    {
        echo PHP_EOL, '== Summary ==', PHP_EOL;
        echo '  ', $this->passed, ' checks passed, ', count($this->failed), ' failed', PHP_EOL;

        foreach ($this->failed as $failed) {
            echo '  FAIL  ', $failed, PHP_EOL;
        }

        return $this->failed === [] ? 0 : 1;
    }
}
