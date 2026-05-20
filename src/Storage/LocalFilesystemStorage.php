<?php

declare(strict_types=1);

namespace Token27\NexusAI\Prompts\Storage;

use const DIRECTORY_SEPARATOR;

use Token27\NexusAI\Prompts\Contract\StorageInterface;
use Token27\NexusAI\Prompts\Exception\StorageException;

final class LocalFilesystemStorage implements StorageInterface
{
    public function __construct(
        private readonly string $basePath = '',
    ) {}

    public function read(string $path): string
    {
        $fullPath = $this->resolvePath($path);

        if (!is_file($fullPath)) {
            throw StorageException::pathNotFound($fullPath);
        }

        $content = file_get_contents($fullPath);

        if ($content === false) {
            throw StorageException::readFailed($fullPath);
        }

        return $content;
    }

    public function exists(string $path): bool
    {
        $fullPath = $this->resolvePath($path);

        return file_exists($fullPath);
    }

    /**
     * @return list<string>
     */
    public function listDirectories(string $path): array
    {
        $fullPath = $this->resolvePath($path);

        if (!is_dir($fullPath)) {
            return [];
        }

        $directories = [];
        $entries = scandir($fullPath);

        if ($entries === false) {
            return [];
        }

        foreach ($entries as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }

            if (is_dir($fullPath . DIRECTORY_SEPARATOR . $entry)) {
                $directories[] = $entry;
            }
        }

        sort($directories);

        return $directories;
    }

    /**
     * @return list<string>
     */
    public function listFiles(string $path): array
    {
        $fullPath = $this->resolvePath($path);

        if (!is_dir($fullPath)) {
            return [];
        }

        $files = [];
        $entries = scandir($fullPath);

        if ($entries === false) {
            return [];
        }

        foreach ($entries as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }

            if (is_file($fullPath . DIRECTORY_SEPARATOR . $entry)) {
                $files[] = $entry;
            }
        }

        sort($files);

        return $files;
    }

    private function resolvePath(string $path): string
    {
        if ($this->basePath === '') {
            return $path;
        }

        return rtrim($this->basePath, '/\\') . DIRECTORY_SEPARATOR . ltrim($path, '/\\');
    }
}
