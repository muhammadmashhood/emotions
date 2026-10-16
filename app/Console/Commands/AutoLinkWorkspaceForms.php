<?php

namespace App\Console\Commands;

use App\Domain\Forms\WorkspaceAutoLinker;
use Illuminate\Console\Command;

class AutoLinkWorkspaceForms extends Command
{
    protected $signature = 'forms:auto-link';

    protected $description = 'Link new Typeform forms from hospital-mapped workspaces (mapping still needs admin review)';

    public function handle(WorkspaceAutoLinker $linker): int
    {
        foreach ($linker->run() as $slug => $count) {
            $this->line("{$slug}: {$count} new form(s) linked");
        }
        $this->info('Auto-link run complete.');

        return self::SUCCESS;
    }
}
