<?php

declare(strict_types=1);

namespace Quorae\SettingsBundle\Service;

use Symfony\Component\Yaml\Yaml;

final class SettingsDefinitionLoader
{
    /** @var array<string, array<string, mixed>>|null */
    private ?array $definitions = null;

    /**
     * @param list<string> $allowedEnvPrefixes
     * @param list<string> $allowedFilePrefixes
     */
    public function __construct(
        private readonly string $definitionsPath,
        private readonly string $projectDir,
        private readonly array $allowedEnvPrefixes = [],
        private readonly array $allowedFilePrefixes = [],
    ) {
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public function loadAll(): array
    {
        if (null !== $this->definitions) {
            return $this->definitions;
        }

        $definitions = [];
        if (!is_dir($this->definitionsPath)) {
            return $this->definitions = [];
        }

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($this->definitionsPath, \FilesystemIterator::SKIP_DOTS),
        );

        foreach ($iterator as $fileInfo) {
            \assert($fileInfo instanceof \SplFileInfo);
            $extension = $fileInfo->getExtension();
            if ('yaml' !== $extension && 'yml' !== $extension) {
                continue;
            }

            $parsed = Yaml::parseFile($fileInfo->getPathname());
            if (!\is_array($parsed) || !isset($parsed['group']) || !\is_string($parsed['group'])) {
                continue;
            }

            $definitions[$parsed['group']] = $parsed;
        }

        return $this->definitions = $definitions;
    }

    /**
     * @param array<string, mixed> $field
     */
    public function resolveDefault(array $field): ?string
    {
        if (isset($field['env_key']) && \is_string($field['env_key'])) {
            $envKey = $field['env_key'];
            $this->assertEnvKeyAllowed($envKey);

            $envValue = $this->readEnv($envKey);
            if (null !== $envValue && '' !== $envValue) {
                return $envValue;
            }
        }

        $default = $field['default'] ?? null;
        if (!\is_string($default)) {
            return null === $default ? null : (string) $default;
        }

        if (str_starts_with($default, 'file://')) {
            return $this->readFileReference(substr($default, 7));
        }

        return $default;
    }

    private function readEnv(string $envKey): ?string
    {
        if (isset($_ENV[$envKey]) && \is_string($_ENV[$envKey])) {
            return $_ENV[$envKey];
        }
        if (isset($_SERVER[$envKey]) && \is_string($_SERVER[$envKey])) {
            return $_SERVER[$envKey];
        }
        $raw = getenv($envKey);

        return false === $raw ? null : $raw;
    }

    private function assertEnvKeyAllowed(string $envKey): void
    {
        if ([] === $this->allowedEnvPrefixes) {
            return;
        }

        foreach ($this->allowedEnvPrefixes as $prefix) {
            if (str_starts_with($envKey, $prefix)) {
                return;
            }
        }

        throw new \InvalidArgumentException(\sprintf(
            'Settings loader: env_key "%s" is not in the allowed prefix list (%s).',
            $envKey,
            implode(', ', $this->allowedEnvPrefixes),
        ));
    }

    private function readFileReference(string $relativePath): ?string
    {
        if (str_contains($relativePath, '..')) {
            throw new \InvalidArgumentException(\sprintf('Settings loader: path traversal rejected in "%s".', $relativePath));
        }

        if ([] !== $this->allowedFilePrefixes) {
            $allowed = false;
            foreach ($this->allowedFilePrefixes as $prefix) {
                if (str_starts_with($relativePath, $prefix)) {
                    $allowed = true;

                    break;
                }
            }

            if (!$allowed) {
                throw new \InvalidArgumentException(\sprintf(
                    'Settings loader: file reference "%s" is outside the allowed directories (%s).',
                    $relativePath,
                    implode(', ', $this->allowedFilePrefixes),
                ));
            }
        }

        $absolute = $this->projectDir.'/'.$relativePath;
        $realPath = realpath($absolute);
        $realProject = realpath($this->projectDir);

        if (false === $realProject) {
            return null;
        }

        if (false === $realPath) {
            return null;
        }

        if (!str_starts_with($realPath, $realProject.\DIRECTORY_SEPARATOR) && $realPath !== $realProject) {
            throw new \InvalidArgumentException(\sprintf('Settings loader: real path "%s" escapes the project directory.', $realPath));
        }

        if (!is_readable($realPath)) {
            throw new \InvalidArgumentException(\sprintf('Settings loader: file "%s" exists but is not readable.', $realPath));
        }

        $content = file_get_contents($realPath);

        return false === $content ? null : $content;
    }
}
