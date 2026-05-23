<?php
/**
 * AutoCoder V4 — Sandbox d'exécution sécurisée
 * Isole les builds dans des conteneurs Docker temporaires.
 * Fallback vers exec() locale si Docker n'est pas disponible.
 */
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';

class BuildSandbox {
    private string $buildDir;
    private bool $useDocker;
    private ?string $containerName = null;

    public function __construct(string $projectFolder) {
        $this->buildDir = AC4_BUILDS_DIR . DIRECTORY_SEPARATOR . basename($projectFolder);
        $this->useDocker = $this->checkDocker();
    }

    /**
     * Exécute une commande de build dans l'environnement isolé
     */
    public function execute(array $command): array {
        $output = [];
        $code = -1;

        if ($this->useDocker) {
            return $this->executeDocker($command);
        }

        // Fallback: exec locale avec sécurisation
        return $this->executeLocal($command);
    }

    public function cleanup(): void {
        if ($this->containerName) {
            $this->runCmd("docker rm -f {$this->containerName} 2>/dev/null");
            $this->containerName = null;
        }
    }

    private function checkDocker(): bool {
        $out = [];
        exec('docker --version 2>&1', $out, $code);
        return $code === 0;
    }

    private function executeDocker(array $command): array {
        $this->containerName = 'ac4_build_' . uniqid() . '_' . bin2hex(random_bytes(4));

        $stack = $command['stack'] ?? 'node';
        $image = $this->getDockerImage($stack);
        $cmd = $command['cmd'];
        $cwd = $command['cwd'] ?? $this->buildDir;

        // Map the build directory into the container
        $hostDir = str_replace('\\', '/', $cwd);
        $workDir = '/app';

        // Use --rm flag for automatic cleanup
        $dockerCmd = sprintf(
            'docker run --name %s --rm -v "%s:%s" -w %s %s sh -c %s 2>&1',
            escapeshellarg($this->containerName),
            escapeshellarg($hostDir),
            $workDir,
            $workDir,
            escapeshellarg($image),
            escapeshellarg($cmd)
        );

        // Set a timeout to prevent runaway containers
        $timeout = $command['timeout'] ?? 120;
        $dockerCmd = "timeout $timeout $dockerCmd";

        exec($dockerCmd, $output, $code);

        return [
            'output' => $output,
            'code' => $code,
            'container' => $this->containerName,
        ];
    }

    private function executeLocal(array $command): array {
        $output = [];
        $code = -1;
        $cmd = $command['cmd'];
        $cwd = $command['cwd'] ?? $this->buildDir;

        // Validate working directory is within builds dir
        $realCwd = realpath($cwd);
        $realBuilds = realpath(AC4_BUILDS_DIR);
        if ($realCwd === false || strpos($realCwd, $realBuilds) !== 0) {
            return [
                'output' => ['ERROR: Working directory outside builds folder'],
                'code' => -1,
                'container' => null,
            ];
        }

        // Set timeout
        $timeout = $command['timeout'] ?? 120;
        $timeoutCmd = PHP_OS_FAMILY === 'Windows'
            ? '' // Windows timeout handled differently
            : "timeout $timeout ";

        $fullCmd = PHP_OS_FAMILY === 'Windows'
            ? "cd /d \"$cwd\" && $cmd"
            : "cd \"$cwd\" && $timeoutCmd$cmd";

        exec($fullCmd, $output, $code);

        return [
            'output' => $output,
            'code' => $code,
            'container' => null,
        ];
    }

    private function getDockerImage(string $stack): string {
        return match ($stack) {
            'node' => 'node:20-alpine',
            'python' => 'python:3.12-slim',
            'go' => 'golang:1.22-alpine',
            'rust' => 'rust:1.77-slim',
            'java', 'kotlin' => 'eclipse-temurin:21-jdk-alpine',
            'flutter' => 'cirrusci/flutter:3.22',
            default => 'node:20-alpine',
        };
    }

    private function runCmd(string $cmd): void {
        exec($cmd);
    }

    public function __destruct() {
        $this->cleanup();
    }
}
