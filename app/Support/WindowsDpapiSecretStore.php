<?php

namespace App\Support;

use App\Contracts\LocalSecretStore;
use RuntimeException;

class WindowsDpapiSecretStore implements LocalSecretStore
{
    public function put(string $path, array $payload): void
    {
        $json = json_encode($payload, JSON_THROW_ON_ERROR);
        $encrypted = $this->runPowerShell(
            <<<'POWERSHELL'
$bytes = [Convert]::FromBase64String([Console]::In.ReadToEnd().Trim())
$plain = [Text.Encoding]::UTF8.GetString($bytes)
$secure = ConvertTo-SecureString -String $plain -AsPlainText -Force
ConvertFrom-SecureString $secure
POWERSHELL,
            base64_encode($json),
        );

        if ($encrypted === '') {
            throw new RuntimeException('Windows DPAPI returned an empty protected value.');
        }

        $directory = dirname($path);

        if (! is_dir($directory)) {
            throw new RuntimeException('The local secret directory does not exist.');
        }

        if (file_put_contents($path, $encrypted, LOCK_EX) === false) {
            throw new RuntimeException('The local secret file could not be written.');
        }
    }

    public function get(string $path): array
    {
        if (! is_file($path)) {
            throw new RuntimeException('The local secret file does not exist.');
        }

        $encrypted = file_get_contents($path);

        if ($encrypted === false || trim($encrypted) === '') {
            throw new RuntimeException('The local secret file is empty.');
        }

        $json = $this->runPowerShell(
            <<<'POWERSHELL'
$encrypted = [Console]::In.ReadToEnd().Trim()
$secure = ConvertTo-SecureString -String $encrypted
$pointer = [Runtime.InteropServices.Marshal]::SecureStringToBSTR($secure)
try {
    [Runtime.InteropServices.Marshal]::PtrToStringBSTR($pointer)
}
finally {
    [Runtime.InteropServices.Marshal]::ZeroFreeBSTR($pointer)
}
POWERSHELL,
            trim($encrypted),
        );

        $payload = json_decode($json, true, 512, JSON_THROW_ON_ERROR);

        if (! is_array($payload)) {
            throw new RuntimeException('The local secret payload is invalid.');
        }

        return $payload;
    }

    private function runPowerShell(string $script, string $input): string
    {
        $descriptors = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];

        $process = proc_open(
            ['powershell.exe', '-NoLogo', '-NoProfile', '-NonInteractive', '-Command', $script],
            $descriptors,
            $pipes,
            base_path(),
            null,
            ['bypass_shell' => true],
        );

        if (! is_resource($process)) {
            throw new RuntimeException('Windows PowerShell could not be started.');
        }

        fwrite($pipes[0], $input);
        fclose($pipes[0]);

        $output = stream_get_contents($pipes[1]);
        $error = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);

        $status = proc_get_status($process);
        $exitCode = $status['exitcode'];
        proc_close($process);

        if ($exitCode !== 0) {
            throw new RuntimeException('Windows DPAPI operation failed: '.trim((string) $error));
        }

        return trim((string) $output);
    }
}
