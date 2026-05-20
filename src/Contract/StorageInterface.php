<?php

declare(strict_types=1);

namespace Token27\NexusAI\Prompts\Contract;

interface StorageInterface
{
    /**
     * Read file contents at the given path.
     *
     * @throws \Token27\NexusAI\Prompts\Exception\StorageException
     */
    public function read(string $path): string;

    /**
     * Check if a file or directory exists at the given path.
     */
    public function exists(string $path): bool;

    /**
     * List subdirectory names within the given path.
     *
     * @return list<string>
     */
    public function listDirectories(string $path): array;

    /**
     * List file names within the given path.
     *
     * @return list<string>
     */
    public function listFiles(string $path): array;
}
