<?php

declare(strict_types=1);

namespace Token27\NexusAI\Prompts\Tests\Unit;

use function dirname;

use PHPUnit\Framework\TestCase;
use Token27\NexusAI\Prompts\Exception\StorageException;
use Token27\NexusAI\Prompts\Storage\LocalFilesystemStorage;

final class LocalFilesystemStorageTest extends TestCase
{
    private string $sourceAPath;

    /** No basePath — uses full absolute paths directly */
    private LocalFilesystemStorage $storage;

    protected function setUp(): void
    {
        $this->sourceAPath = dirname(__DIR__) . '/fixtures/prompts/source-a';
        // No basePath — we pass full absolute paths
        $this->storage = new LocalFilesystemStorage('');
    }

    public function testReadExistingFile(): void
    {
        $content = $this->storage->read($this->sourceAPath . '/websearch/v1.0.0/es.json');

        static::assertIsString($content);
        static::assertNotEmpty($content);

        $decoded = json_decode($content, true);
        static::assertIsArray($decoded);
        static::assertArrayHasKey('meta', $decoded);
    }

    public function testReadNonExistingFileThrowsException(): void
    {
        $this->expectException(StorageException::class);

        $this->storage->read($this->sourceAPath . '/nonexistent/path/file.json');
    }

    public function testExistsReturnsTrueForFile(): void
    {
        static::assertTrue($this->storage->exists($this->sourceAPath . '/websearch/v1.0.0/es.json'));
    }

    public function testExistsReturnsFalseForMissingFile(): void
    {
        static::assertFalse($this->storage->exists($this->sourceAPath . '/nothing/here.json'));
    }

    public function testExistsReturnsTrueForDirectory(): void
    {
        static::assertTrue($this->storage->exists($this->sourceAPath . '/websearch'));
    }

    public function testListDirectoriesReturnsSubdirs(): void
    {
        $dirs = $this->storage->listDirectories($this->sourceAPath);

        static::assertIsArray($dirs);
        static::assertContains('websearch', $dirs);
    }

    public function testListDirectoriesReturnsEmptyForMissingPath(): void
    {
        $dirs = $this->storage->listDirectories($this->sourceAPath . '/nonexistent');

        static::assertSame([], $dirs);
    }

    public function testListFilesReturnsJsonFiles(): void
    {
        $files = $this->storage->listFiles($this->sourceAPath . '/websearch/v1.0.0');

        static::assertIsArray($files);
        static::assertContains('en.json', $files);
        static::assertContains('es.json', $files);
    }

    public function testStorageWithBasepathResolvesRelativePaths(): void
    {
        $storage = new LocalFilesystemStorage($this->sourceAPath);

        static::assertTrue($storage->exists('websearch/v1.0.0/es.json'));

        $content = $storage->read('websearch/v1.0.0/es.json');
        static::assertNotEmpty($content);
    }
}
