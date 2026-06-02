<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class BrowsershotEnv extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'browsershot:env';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Dump the environment Browsershot will run in (node, npm, chrome, paths)';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $chromePath = config('browsershot.chrome_path');

        $rows = [
            ['Config: node_module_path', config('browsershot.node_module_path')],
            ['Config: chrome_path', $chromePath ?: '(null — bundled Chromium)'],
            ['Config: node_binary', config('browsershot.node_binary') ?: '(null — uses PATH)'],
            ['Config: npm_binary', config('browsershot.npm_binary') ?: '(null — uses PATH)'],
            ['PATH', getenv('PATH') ?: '(empty)'],
            ['node version', $this->runShell('node -v')],
            ['npm version', $this->runShell('npm -v')],
            ['node binary location', $this->runShell('command -v node')],
            ['npm global root', $this->runShell('npm root -g')],
            ['chrome exists', $chromePath ? (is_file($chromePath) ? 'yes' : 'NO — not found') : 'n/a (bundled)'],
            ['chrome version', $chromePath && is_file($chromePath) ? $this->runShell(escapeshellarg($chromePath) . ' --version') : 'n/a'],
        ];

        $this->table(['Setting', 'Value'], $rows);

        return self::SUCCESS;
    }

    /**
     * Run a shell command and return its trimmed output (or an error marker).
     */
    private function runShell(string $command): string
    {
        exec($command . ' 2>&1', $output, $exitCode);
        $result = trim(implode("\n", $output));

        return $exitCode === 0 ? ($result ?: '(no output)') : '✗ failed: ' . $result;
    }
}
