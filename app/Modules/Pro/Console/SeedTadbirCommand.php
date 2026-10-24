<?php

namespace App\Modules\Pro\Console;

use App\Modules\Pro\Support\GovernanceDefaults;
use Illuminate\Console\Command;

/** php artisan pro:seed-tadbir — (re)insert missing governance defaults; never overwrites edits. */
class SeedTadbirCommand extends Command
{
    protected $signature = 'pro:seed-tadbir';

    protected $description = 'Insert missing default stewardship, quality rules, classification, dictionary and retention rows';

    public function handle(): int
    {
        GovernanceDefaults::seed();
        $this->components->info('Lalai tadbir urus data dikemas kini.');

        return self::SUCCESS;
    }
}
