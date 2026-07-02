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

        // Actually LOAD puppeteer (not just resolve its path) the same way
        // browser.cjs does. This triggers puppeteer's internal ESM import of
        // puppeteer-core, which NODE_PATH does NOT satisfy — so it catches the
        // "directory must be named node_modules" class of failure too.
        $command = sprintf(
            'NODE_PATH=%s node -e %s 2>&1',
            escapeshellarg($modulePath),
            escapeshellarg('require("puppeteer"); console.log("fully loaded from " + require.resolve("puppeteer"))')
        );

        exec($command, $output, $exitCode);

        if ($exitCode === 0) {
            $this->info('✓ node fully loaded puppeteer: ' . implode("\n", $output));

            return self::SUCCESS;
        }

        $this->error('✗ node resolved puppeteer\'s path but failed to load it from this NODE_PATH:');
        $this->line('  ' . implode("\n  ", $output));
        $this->line('  (If this mentions puppeteer-core / ERR_MODULE_NOT_FOUND, the module');
        $this->line('   directory must be named exactly "node_modules" so ESM resolution works.)');

        return self::FAILURE;
    }
}
