<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;

class CleanupOrphanUsers extends Command
{
    protected $signature = 'users:cleanup-orphans {--dry-run : Csak listáz, nem töröl} {--force : Megerősítés nélkül töröl}';

    protected $description = 'Háztartás nélküli (árva) felhasználói fiókok törlése';

    public function handle(): int
    {
        $orphans = User::query()->whereNull('household_id')->get();

        if ($orphans->isEmpty()) {
            $this->info('Nincs árva felhasználó.');

            return self::SUCCESS;
        }

        $this->table(
            ['ID', 'Felhasználónév', 'Név'],
            $orphans->map(fn (User $u) => [
                $u->id,
                $u->username,
                trim($u->first_name.' '.$u->last_name),
            ])->all(),
        );

        if ($this->option('dry-run')) {
            $this->warn('Dry-run — nem történt törlés.');

            return self::SUCCESS;
        }

        if (! $this->option('force') && ! $this->confirm('Biztosan törlöd ezeket a fiókokat?', false)) {
            return self::SUCCESS;
        }

        $count = 0;
        foreach ($orphans as $user) {
            $user->tokens()->delete();
            $user->delete();
            $count++;
        }

        $this->info("{$count} árva fiók törölve.");

        return self::SUCCESS;
    }
}
