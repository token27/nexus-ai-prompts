# Storage Backends

`nexus-ai-prompts` reads prompt JSON files through a `StorageInterface` abstraction. Two implementations are provided: `LocalFilesystemStorage` (zero extra deps) and `FlysystemStorage` (League Flysystem v3).

## StorageInterface

```php
namespace Token27\NexusAI\Prompts\Contract;

interface StorageInterface
{
    public function read(string $path): string;
    public function exists(string $path): bool;
    /** @return list<string> list of subdirectory names */
    public function listDirectories(string $path): array;
    /** @return list<string> list of filenames (e.g. 'en.json') */
    public function listFiles(string $path): array;
}
```

## LocalFilesystemStorage

**No external dependencies.** Uses native PHP file functions (`file_get_contents`, `is_dir`, `scandir`).

### With no basePath (recommended when using `registerDirectory` with full absolute paths)

```php
use Token27\NexusAI\Prompts\Storage\LocalFilesystemStorage;

$storage = new LocalFilesystemStorage('');
$registry = new PromptRegistry(loader: ..., defaultStorage: $storage, ...);

// registerDirectory passes full absolute paths, storage uses them as-is
$registry->registerDirectory('/var/app/prompts/article', 'article', 'my-lib');
```

### With a basePath (convenient for relative paths)

```php
$storage = new LocalFilesystemStorage('/var/app/prompts');
// Paths are resolved relative to basePath
// storage->read('article/research/v1.0.0/es.json')
// → reads '/var/app/prompts/article/research/v1.0.0/es.json'
```

### Exceptions

Throws `StorageException` when a file cannot be read.

## FlysystemStorage

Backed by [League Flysystem v3](https://flysystem.thephpleague.com/). Supports local, S3, Google Cloud, SFTP and any other Flysystem adapter.

### Local adapter

```php
use Token27\NexusAI\Prompts\Storage\FlysystemStorage;
use League\Flysystem\Filesystem;
use League\Flysystem\Local\LocalFilesystemAdapter;

$filesystem = new Filesystem(new LocalFilesystemAdapter('/var/app/prompts'));
$storage = new FlysystemStorage($filesystem);
```

### S3 adapter

```php
use League\Flysystem\AwsS3V3\AwsS3V3Adapter;
use Aws\S3\S3Client;

$client = new S3Client(['region' => 'us-east-1', 'credentials' => ...]);
$filesystem = new Filesystem(new AwsS3V3Adapter($client, 'my-prompts-bucket'));
$storage = new FlysystemStorage($filesystem);

$registry->registerStorage('remote-source', $storage);
```

### Caching adapter (Flysystem)

```php
// Use Flysystem's caching adapter for expensive remote storage
$filesystem = new Filesystem(
    new CachedAdapter(new AwsS3V3Adapter(...), new FilesystemCache('/tmp/prompts'))
);
$storage = new FlysystemStorage($filesystem);
```

## Choosing a Backend

| Scenario | Recommended backend |
|----------|-------------------|
| Local development, no remote storage | `LocalFilesystemStorage('')` |
| Prompts in the same Composer package | `LocalFilesystemStorage('')` with full paths |
| Production app with remote prompts | `FlysystemStorage` with S3/GCS adapter |
| Tests (fixture files) | `LocalFilesystemStorage('')` with absolute fixture paths |
| Per-source override for one library | `registerStorage('source', ...)` |

## Implementing a Custom Backend

Implement `StorageInterface` with four methods:

```php
use Token27\NexusAI\Prompts\Contract\StorageInterface;
use Token27\NexusAI\Prompts\Exception\StorageException;

final class RedisStorage implements StorageInterface
{
    public function __construct(private readonly \Redis $redis) {}

    public function read(string $path): string
    {
        $content = $this->redis->get($path);
        if ($content === false) {
            throw StorageException::cannotRead($path);
        }
        return $content;
    }

    public function exists(string $path): bool
    {
        return (bool) $this->redis->exists($path);
    }

    public function listDirectories(string $path): array
    {
        // Implementation depends on your Redis key structure
        return [];
    }

    public function listFiles(string $path): array
    {
        return $this->redis->lRange($path . ':files', 0, -1);
    }
}
```

Then register it:

```php
$registry->registerStorage('my-source', new RedisStorage($redis));
```
