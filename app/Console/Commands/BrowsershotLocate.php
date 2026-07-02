<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class BrowsershotLocate extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'browsershot:locate';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Hunt for node_modules, puppeteer, and any Chrome/Chromium binary on the runtime box';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $home = getenv('HOME') ?: '/var/www';

        $this->components->info('What persisted to the runtime filesystem?');
        $this->line('Top of ' . base_path() . ':');
        $this->line('  ' . ($this->runShell('ls -la ' . escapeshellarg(base_path()) . " | awk '{print \$9}' | grep -v '^$' | tr '\\n' ' '")));
        $this->line('node_modules exists:           ' . $this->yn(is_dir(base_path('node_modules'))));
        $this->line('node_modules/puppeteer exists: ' . $this->yn(is_dir(base_path('node_modules/puppeteer'))));
        $this->line('public/build exists:           ' . $this->yn(is_dir(base_path('public/build'))));

        $this->components->info('Puppeteer Chromium cache locations');
        foreach ([
            'PUPPETEER_CACHE_DIR env' => getenv('PUPPETEER_CACHE_DIR') ?: null,
            'home cache' => $home . '/.cache/puppeteer',
            'project-local cache' => base_path('node_modules/.cache/puppeteer'),
        ] as $label => $path) {
            if (! $path) {
                $this->line(sprintf('%-22s (not set)', $label . ':'));
                continue;
            }
            $this->line(sprintf('%-22s %s  [%s]', $label . ':', $path, is_dir($path) ? 'EXISTS' : 'missing'));
            if (is_dir($path)) {
                $this->line('  contents: ' . $this->runShell('ls -1 ' . escapeshellarg($path) . " | tr '\\n' ' '"));
            }
        }

        $this->components->info('Any chrome/chromium binary on the system?');
        $this->line('which chromium / chrome / google-chrome:');
        $this->line('  ' . $this->runShell('command -v chromium chromium-browser google-chrome google-chrome-stable chrome 2>/dev/null | tr "\\n" " "'));
        $this->line('find under puppeteer cache:');
        $this->line('  ' . $this->runShell('find ' . escapeshellarg($home . '/.cache/puppeteer') . ' ' . escapeshellarg(base_path('node_modules')) . ' -type f \( -name chrome -o -name "chrome-headless-shell" \) 2>/dev/null | head -n 5 | tr "\\n" " "'));

        return self::SUCCESS;
    }

    private function yn(bool $value): string
    {
        return $value ? '✓ yes' : '✗ no';
    }

    private function runShell(string $command): string
    {
        exec($command . ' 2>&1', $output, $exitCode);
        $result = trim(implode("\n", $output));

        return $result !== '' ? $result : '(nothing found)';
    }
}
