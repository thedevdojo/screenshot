<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Str;

class ScreenshotKey extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'screenshot:key
                            {--show : Display the generated key instead of writing it}
                            {--force : Overwrite an existing key without confirmation}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Generate a strong SCREENSHOT_API_KEY and set it in the .env file';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $key = $this->generateKey();

        if ($this->option('show')) {
            $this->line('<comment>' . $key . '</comment>');

            return self::SUCCESS;
        }

        if (! $this->setKeyInEnvironmentFile($key)) {
            return self::FAILURE;
        }

        config(['screenshot.api_key' => $key]);

        $this->components->info('Screenshot API key set successfully.');
        $this->line('  Send it with requests as: <comment>Authorization: Bearer ' . $key . '</comment>');

        return self::SUCCESS;
    }

    /**
     * Generate a strong random API key.
     */
    protected function generateKey(): string
    {
        return Str::random(48);
    }

    /**
     * Write the key into the .env file, replacing any existing value.
     */
    protected function setKeyInEnvironmentFile(string $key): bool
    {
        $path = base_path('.env');

        if (! is_file($path)) {
            $this->components->error('No .env file found at ' . $path . '. Create one (e.g. copy .env.example) and try again.');

            return false;
        }

        $current = config('screenshot.api_key');

        if (! empty($current) && ! $this->option('force')) {
            if (! $this->components->confirm('A SCREENSHOT_API_KEY already exists. Overwrite it?', false)) {
                $this->components->warn('Key left unchanged.');

                return false;
            }
        }

        $contents = file_get_contents($path);

        if (preg_match('/^SCREENSHOT_API_KEY=.*$/m', $contents)) {
            $contents = preg_replace('/^SCREENSHOT_API_KEY=.*$/m', 'SCREENSHOT_API_KEY=' . $key, $contents);
        } else {
            $contents = rtrim($contents, "\n") . "\n\nSCREENSHOT_API_KEY=" . $key . "\n";
        }

        file_put_contents($path, $contents);

        return true;
    }
}
