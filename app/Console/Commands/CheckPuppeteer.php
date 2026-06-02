<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Spatie\Browsershot\Browsershot;

class CheckPuppeteer extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'browsershot:check';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Verify puppeteer is installed where Browsershot expects it';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $modulePath = config('browsershot.node_module_path');
        $puppeteerPath = $modulePath . '/puppeteer';
        $packageJson = $puppeteerPath . '/package.json';

        $this->line('Browsershot NODE_PATH target: <comment>' . $modulePath . '</comment>');

        if (! is_dir($modulePath)) {
            $this->error('✗ node_modules directory does not exist. Run `npm ci` during deploy.');

            return self::FAILURE;
        }

        if (! is_dir($puppeteerPath)) {
            $this->error('✗ puppeteer is NOT installed at ' . $puppeteerPath);
            $this->line('  Add "puppeteer" to package.json and run `npm ci`.');

            return self::FAILURE;
        }

        $version = 'unknown';
        if (is_file($packageJson)) {
            $version = json_decode(file_get_contents($packageJson), true)['version'] ?? 'unknown';
        }

        $this->info('✓ puppeteer found (v' . $version . ') at ' . $puppeteerPath);

        // Prove node can actually resolve the module from this NODE_PATH.
        $command = sprintf(
            'NODE_PATH=%s node -e %s 2>&1',
            escapeshellarg($modulePath),
            escapeshellarg('require.resolve("puppeteer"); console.log(require.resolve("puppeteer"))')
        );

        exec($command, $output, $exitCode);

        if ($exitCode === 0) {
            $this->info('✓ node resolved puppeteer: ' . implode("\n", $output));

            return self::SUCCESS;
        }

        $this->error('✗ node could not resolve puppeteer from this NODE_PATH:');
        $this->line('  ' . implode("\n  ", $output));

        return self::FAILURE;
    }
}
