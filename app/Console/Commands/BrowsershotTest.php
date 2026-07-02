<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Spatie\Browsershot\Browsershot;
use Throwable;

class BrowsershotTest extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'browsershot:test {--url= : Screenshot a URL instead of inline HTML}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Take a real test screenshot end-to-end using the configured Browsershot settings';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $outputPath = storage_path('app/browsershot-test.png');

        $this->line('Taking test screenshot using:');
        $this->line('  node_module_path: <comment>' . config('browsershot.node_module_path') . '</comment>');
        $this->line('  chrome_path:      <comment>' . (config('browsershot.chrome_path') ?: 'bundled') . '</comment>');

        try {
            $browsershot = $this->option('url')
                ? Browsershot::url($this->option('url'))
                : Browsershot::html('<html><body style="font-family:sans-serif;padding:40px"><h1>Browsershot OK</h1></body></html>');

            $browsershot
                ->setNodeModulePath(config('browsershot.node_module_path'))
                ->windowSize(1200, 630)
                ->newHeadless()
                ->noSandbox()
                ->timeout(120);

            if ($chromePath = config('browsershot.chrome_path')) {
                $browsershot->setChromePath($chromePath);
            }

            $browsershot->save($outputPath);
        } catch (Throwable $e) {
            $this->error('✗ Screenshot failed:');
            $this->line($e->getMessage());

            return self::FAILURE;
        }

        if (! is_file($outputPath) || filesize($outputPath) === 0) {
            $this->error('✗ Command returned but no image was written to ' . $outputPath);

            return self::FAILURE;
        }

        $this->info('✓ Screenshot written: ' . $outputPath . ' (' . filesize($outputPath) . ' bytes)');

        return self::SUCCESS;
    }
}
