<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Actions\ResyncHandbook;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class HandbookDocumentSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(ResyncHandbook $resyncHandbook): void
    {
        $resyncHandbook->handle();
    }
}
