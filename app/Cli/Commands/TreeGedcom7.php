<?php

/**
 * webtrees: online genealogy
 * Copyright (C) 2026 webtrees development team
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 * You should have received a copy of the GNU General Public License
 * along with this program. If not, see <https://www.gnu.org/licenses/>.
 */

declare(strict_types=1);

namespace Fisharebest\Webtrees\Cli\Commands;

use Fisharebest\Webtrees\DB;
use Fisharebest\Webtrees\Gedcom7\Gedcom7Storage;
use Fisharebest\Webtrees\Gedcom7\Gedcom7TreeConverter;
use Fisharebest\Webtrees\Tree;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Throwable;

use function count;

/**
 * webtrees7: convert a tree, so that it stores GEDCOM 7.0.
 */
final class TreeGedcom7 extends AbstractCommand
{
    public function __construct(private readonly Gedcom7TreeConverter $gedcom7_tree_converter)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->setName(name: 'tree-gedcom7')
            ->addArgument(name: 'tree-name', mode: InputArgument::REQUIRED, description: 'The name of the tree')
            ->setDescription(description: 'Convert a tree, so that it stores GEDCOM 7.0');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle(input: $input, output: $output);

        $tree_name = $this->stringArgument(input: $input, name: 'tree-name');

        $tree = DB::table('gedcom')
            ->where('gedcom_name', '=', $tree_name)
            ->get()
            ->map(Tree::fromDB(...))
            ->first();

        if ($tree === null) {
            $io->error(message: 'Tree "' . $tree_name . '" not found.');

            return self::FAILURE;
        }

        if (Gedcom7Storage::isGedcom7Tree($tree)) {
            $io->info(message: 'Tree "' . $tree_name . '" already stores GEDCOM 7.0.');

            return self::SUCCESS;
        }

        try {
            $renamed = $this->gedcom7_tree_converter->convert($tree);
        } catch (Throwable $ex) {
            $io->error(message: $ex->getMessage());

            return self::FAILURE;
        }

        foreach ($renamed as $old => $new) {
            $io->writeln('Renamed ' . $old . ' to ' . $new);
        }

        $io->success(message: 'Tree "' . $tree_name . '" now stores GEDCOM 7.0.  ' . count($renamed) . ' XREFs were renamed.');

        return self::SUCCESS;
    }
}
