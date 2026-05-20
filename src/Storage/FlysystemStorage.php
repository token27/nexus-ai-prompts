<?php

declare(strict_types=1);

namespace Token27\NexusAI\Prompts\Storage;

use League\Flysystem\FilesystemException;
use League\Flysystem\FilesystemOperator;
use Token27\NexusAI\Prompts\Contract\StorageInterface;
use Token27\NexusAI\Prompts\Exception\StorageException;

final class FlysystemStorage implements StorageInterface
{
    public function __construct(
        private readonly FilesystemOperator $filesystem,
    ) {}

    public function read(string $path): string
    {
        try {
            return $this->filesystem->read($path);
        } catch (FilesystemException $e) {
            throw StorageException::readFailed($path, $e);
        }
    }

    public function exists(string $path): bool
    {
        try {
            return $this->filesystem->fileExists($path)
                || $this->filesystem->directoryExists($path);
        } catch (FilesystemException) {
            return false;
        }
    }

    /**
     * @return list<string>
     */
    public function listDirectories(string $path): array
    {
        $directories = [];

        try {
            $listing = $this->filesystem->listContents($path, false);

            foreach ($listing as $item) {
                if ($item->isDir()) {
                    $directories[] = basename($item->path());
                }
            }
        } catch (FilesystemException) {
            return [];
        }

        sort($directories);

        return $directories;
    }

    /**
     * @return list<string>
     */
    public function listFiles(string $path): array
    {
        $files = [];

        try {
            $listing = $this->filesystem->listContents($path, false);

            foreach ($listing as $item) {
                if ($item->isFile()) {
                    $files[] = basename($item->path());
                }
            }
        } catch (FilesystemException) {
            return [];
        }

        sort($files);

        return $files;
    }
}
