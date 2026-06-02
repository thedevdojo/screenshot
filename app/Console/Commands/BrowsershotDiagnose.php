<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class BrowsershotDiagnose extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'browsershot:diagnose';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Determine WHY Chrome fails to launch: arch mismatch, missing libraries, or something else';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $home = getenv('HOME') ?: '/var/www';

        // 1. What CPU architecture is the runtime?
        $this->components->info('Runtime architecture');
        $runtimeArch = $this->runShell('uname -m');
        $this->line('  uname -m:   <comment>' . $runtimeArch . '</comment>');
        $this->line('  uname -a:   ' . $this->runShell('uname -a'));
        $this->line('  libc:       ' . $this->detectLibc());

        // 2. Which Chrome binaries can we find?
        $this->components->info('Chrome / Chromium binaries Browsershot might use');

        $candidates = [];
        if ($configured = config('browsershot.chrome_path')) {
            $candidates[] = $configured;
        }

        $found = $this->runShell(
            'find '
            . escapeshellarg($home . '/.cache/puppeteer') . ' '
            . escapeshellarg((string) config('browsershot.node_module_path')) . ' '
            . escapeshellarg(base_path('node_modules'))
            . ' -type f \( -name chrome -o -name "chrome-headless-shell" \) 2>/dev/null'
        );
        foreach (preg_split('/\r?\n/', $found) as $line) {
            $line = trim($line);
            if ($line !== '' && $line !== '(nothing found)') {
                $candidates[] = $line;
            }
        }

        $candidates = array_values(array_unique(array_filter($candidates)));

        if (empty($candidates)) {
            $this->components->error('No chrome / chrome-headless-shell binary found at all.');
            $this->line('  → puppeteer never downloaded Chromium. Run `npx puppeteer browsers install chrome` on the runtime.');

            return self::FAILURE;
        }

        $verdicts = [];

        foreach ($candidates as $bin) {
            $this->newLine();
            $this->line('<options=bold>► ' . $bin . '</>');

            if (! is_file($bin)) {
                $this->line('  <fg=yellow>does not exist (stale path)</>');
                continue;
            }

            // 3a. What architecture is the binary itself?
            $fileOut = $this->runShell('file -b ' . escapeshellarg($bin));
            $this->line('  file:       ' . $fileOut);
            $binArch = $this->archFromFile($fileOut);
            $archMatches = $this->archMatches($runtimeArch, $binArch);

            $this->line(sprintf(
                '  binary arch: %s  vs  runtime %s  →  %s',
                $binArch ?: 'unknown',
                $runtimeArch,
                $archMatches === null ? '<fg=yellow>could not compare</>' : ($archMatches ? '<info>match</info>' : '<fg=red>MISMATCH</>')
            ));

            // 3b. Are its shared libraries satisfiable?
            $ldd = $this->runShell('ldd ' . escapeshellarg($bin) . ' 2>&1');
            $lddAvailable = stripos($ldd, 'ldd: command not found') === false && stripos($ldd, 'ldd: not found') === false;
            $missing = [];
            if ($lddAvailable) {
                foreach (preg_split('/\r?\n/', $ldd) as $l) {
                    // A genuine missing lib looks like "libfoo.so.1 => not found".
                    if (str_contains($l, '=>') && stripos($l, 'not found') !== false) {
                        $missing[] = trim($l);
                    }
                }
            }
            if (! $lddAvailable) {
                $this->line('  ldd:        (ldd not installed — cannot check libraries; rely on exec result below)');
            } elseif (str_contains($ldd, 'not a dynamic executable') || str_contains($ldd, 'not a dynamic')) {
                $this->line('  ldd:        (static or unreadable)');
            } elseif ($missing) {
                $this->line('  missing libs: <fg=red>' . count($missing) . '</>');
                foreach ($missing as $m) {
                    $this->line('    - ' . $m);
                }
            } else {
                $this->line('  missing libs: <info>none</info>');
            }

            // 3c. Actually try to run it — the ground truth.
            $exec = $this->runShellWithCode(escapeshellarg($bin) . ' --no-sandbox --headless=new --version 2>&1');
            $this->line('  exec --version exit code: ' . ($exec['code'] === 0 ? '<info>0</info>' : '<fg=red>' . $exec['code'] . '</>'));
            if ($exec['output'] !== '') {
                $this->line('  exec output: ' . str_replace("\n", "\n               ", $exec['output']));
            }

            // 3d. Verdict for this binary.
            $verdict = $this->verdict($exec, $archMatches, $missing);
            $verdicts[$bin] = $verdict;
            $this->line('  <options=bold>VERDICT:</> ' . $verdict['label']);
        }

        // 4. Overall conclusion.
        $this->newLine();
        $this->components->info('Conclusion');

        $working = array_filter($verdicts, fn ($v) => $v['ok']);
        if ($working) {
            $bin = array_key_first($working);
            $this->line('  <info>✓ A working Chrome binary exists:</info> ' . $bin);
            $this->line('  → Set this in your env so Browsershot uses it directly:');
            $this->line('      <comment>BROWSERSHOT_CHROME_PATH=' . $bin . '</comment>');

            return self::SUCCESS;
        }

        $codes = array_unique(array_map(fn ($v) => $v['code'], $verdicts));
        if (in_array('arch', $codes, true)) {
            $this->line('  <fg=red>✗ ARCHITECTURE MISMATCH.</> The downloaded Chrome is for a different CPU than this runtime.');
            $this->line('    Build machine and runtime architectures differ. Install Chrome on the RUNTIME arch:');
            $this->line('      <comment>npx puppeteer browsers install chrome</comment>   (run on the runtime, not the builder)');
            $this->line('    or force the platform during build:');
            $this->line('      <comment>npx @puppeteer/browsers install chrome --platform ' . ($runtimeArch === 'x86_64' ? 'linux' : 'linux_arm') . '</comment>');
        } elseif (in_array('libs', $codes, true)) {
            $this->line('  <fg=red>✗ MISSING SHARED LIBRARIES.</> Chrome is the right arch but its dependencies are absent.');
            $this->line('    Install the libs the ldd output flagged above (commonly libnss3, libatk-1.0, libgbm1, libasound2, libxkbcommon, libpangocairo).');
            $this->line('    On a managed host where you cannot apt-install, prefer chrome-headless-shell (lighter deps) and drop ->newHeadless().');
        } else {
            $this->line('  <fg=yellow>? Inconclusive.</> Chrome failed to run but it is not a clean arch or lib problem.');
            $this->line('    Read the exec output above — that is the literal reason the kernel/Chrome rejected it.');
        }

        return self::FAILURE;
    }

    /** Decide what went wrong for a single binary. */
    private function verdict(array $exec, ?bool $archMatches, array $missing): array
    {
        if ($exec['code'] === 0) {
            return ['ok' => true, 'code' => 'ok', 'label' => '<info>WORKS ✓</info>'];
        }
        if ($archMatches === false) {
            return ['ok' => false, 'code' => 'arch', 'label' => '<fg=red>arch mismatch</>'];
        }
        if (! empty($missing)) {
            return ['ok' => false, 'code' => 'libs', 'label' => '<fg=red>missing libraries</>'];
        }
        // ENOEXEC surfaces as a shell "syntax error" when the kernel can't exec the binary.
        if (stripos($exec['output'], 'syntax error') !== false || stripos($exec['output'], 'cannot execute binary') !== false) {
            return ['ok' => false, 'code' => 'arch', 'label' => '<fg=red>arch mismatch (ENOEXEC)</>'];
        }

        return ['ok' => false, 'code' => 'other', 'label' => '<fg=yellow>fails — see exec output</>'];
    }

    /** Extract a normalized arch token from `file` output. */
    private function archFromFile(string $fileOut): ?string
    {
        $fileOut = strtolower($fileOut);
        if (str_contains($fileOut, 'x86-64') || str_contains($fileOut, 'x86_64')) {
            return 'x86_64';
        }
        if (str_contains($fileOut, 'aarch64') || str_contains($fileOut, 'arm aarch64') || str_contains($fileOut, 'arm64')) {
            return 'aarch64';
        }
        if (str_contains($fileOut, 'intel 80386') || str_contains($fileOut, 'i386')) {
            return 'i386';
        }

        return null;
    }

    /** Compare runtime arch (uname -m) to the binary's arch. Null = can't tell. */
    private function archMatches(string $runtimeArch, ?string $binArch): ?bool
    {
        if (! $binArch) {
            return null;
        }
        $normalize = fn (string $a) => match (strtolower($a)) {
            'x86_64', 'amd64', 'x86-64' => 'x86_64',
            'aarch64', 'arm64' => 'aarch64',
            default => strtolower($a),
        };

        return $normalize($runtimeArch) === $normalize($binArch);
    }

    private function detectLibc(): string
    {
        $out = $this->runShell('ldd --version 2>&1 | head -n 1');
        if (stripos($out, 'musl') !== false) {
            return 'musl (Alpine) — glibc-built Chrome will NOT run here';
        }
        if (stripos($out, 'glibc') !== false || stripos($out, 'gnu libc') !== false) {
            return 'glibc — ' . $out;
        }

        return $out ?: 'unknown';
    }

    private function runShell(string $command): string
    {
        exec($command . ' 2>&1', $output, $exitCode);
        $result = trim(implode("\n", $output));

        return $result !== '' ? $result : '(nothing found)';
    }

    private function runShellWithCode(string $command): array
    {
        $output = [];
        $code = 0;
        exec($command, $output, $code);

        return ['output' => trim(implode("\n", $output)), 'code' => $code];
    }
}
