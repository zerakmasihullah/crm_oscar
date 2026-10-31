<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;

class KitInstallCommand extends Command
{
    protected $signature = 'kit:install
                            {--no-interaction : Run without prompts}
                            {--seed : Seed demo owner after migrate}
                            {--offline : Enable offline foundation in .env}
                            {--api : Enable Sanctum API stub in .env}';

    protected $description = 'Interactive first-run setup for the Yaqeen MIS starter kit';

    public function handle(): int
    {
        $this->info('Yaqeen MIS starter kit — install');

        if (! File::exists(base_path('.env'))) {
            File::copy(base_path('.env.example'), base_path('.env'));
            $this->line('Created .env from .env.example');
        }

        if (blank(config('app.key'))) {
            Artisan::call('key:generate', [], $this->output);
        }

        $locale = $this->option('no-interaction')
            ? config('app.locale', 'fa')
            : $this->choice('Default locale', ['fa', 'ps', 'en'], config('app.locale', 'fa'));

        $this->setEnvValue('APP_LOCALE', $locale);

        $enableOffline = $this->option('offline') || (
            ! $this->option('no-interaction') && $this->confirm('Enable offline / PWA foundation (KIT_OFFLINE)?', false)
        );
        $enableApi = $this->option('api') || (
            ! $this->option('no-interaction') && $this->confirm('Enable Sanctum API stub (KIT_API)?', false)
        );

        $this->setEnvValue('KIT_OFFLINE', $enableOffline ? 'true' : 'false');
        $this->setEnvValue('KIT_API', $enableApi ? 'true' : 'false');

        if ($this->confirm('Run migrations now?', true) || $this->option('no-interaction')) {
            Artisan::call('migrate', ['--force' => true], $this->output);
        }

        $shouldSeed = $this->option('seed') || (
            ! $this->option('no-interaction') && $this->confirm('Seed Owner + demo data?', true)
        );

        if ($shouldSeed) {
            Artisan::call('db:seed', ['--force' => true], $this->output);
            Artisan::call('mis:sync-permissions', [], $this->output);
        }

        $this->newLine();
        $this->info('Done. Next: npm install && npm run build && php artisan serve');
        $this->line('Login: admin@example.com / password');

        return self::SUCCESS;
    }

    protected function setEnvValue(string $key, string $value): void
    {
        $path = base_path('.env');
        $contents = File::get($path);
        $line = $key.'='.$value;

        if (preg_match("/^{$key}=.*/m", $contents)) {
            $contents = preg_replace("/^{$key}=.*/m", $line, $contents) ?? $contents;
        } else {
            $contents .= PHP_EOL.$line.PHP_EOL;
        }

        File::put($path, $contents);
    }
}
