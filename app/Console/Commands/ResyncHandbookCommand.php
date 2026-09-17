<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Actions\ResyncHandbook;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('handbook:resync')]
#[Description('Upsert handbook pages from the catalog, rebuild chunks, and remove pages the catalog dropped')]
class ResyncHandbookCommand extends Command
{
    public function handle(ResyncHandbook $resyncHandbook): int
    {
        $resyncHandbook->handle();

        $this->info('Handbook documents resynced.');

        return self::SUCCESS;
    }
}
