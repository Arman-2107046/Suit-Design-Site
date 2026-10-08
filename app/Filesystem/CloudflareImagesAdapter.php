<?php

namespace App\Filesystem;

use App\Services\Cloudflare\CloudflareImages;
use App\Support\ColorProfile;
use League\Flysystem\Config;
use League\Flysystem\FileAttributes;
use League\Flysystem\FilesystemAdapter;
use League\Flysystem\UnableToCheckExistence;
use League\Flysystem\UnableToCopyFile;
use League\Flysystem\UnableToCreateDirectory;
use League\Flysystem\UnableToDeleteFile;
use League\Flysystem\UnableToMoveFile;
use League\Flysystem\UnableToReadFile;
use League\Flysystem\UnableToRetrieveMetadata;
use League\Flysystem\UnableToSetVisibility;
use League\Flysystem\UnableToWriteFile;
use Throwable;

/*
 * Lets Cloudflare Images stand in as a Laravel disk, so a Filament FileUpload
 * pointed at disk('cloudflare') stores, previews and deletes as it would on
 * any other disk.
 *
 * A file's path is its image id ("homepage/01M2….png"). Images has no folders,
 * no listings and no visibility — every image is public — so those parts of
 * the contract are answered as simply as they truthfully can be.
 */
class CloudflareImagesAdapter implements FilesystemAdapter
{
    public function __construct(private readonly CloudflareImages $images) {}

    /** Laravel's Storage::url() calls this when the adapter provides it. */
    public function getUrl(string $path): string
    {
        return $this->images->url($path);
    }

    public function fileExists(string $path): bool
    {
        try {
            return $this->images->exists($path);
        } catch (Throwable $e) {
            throw UnableToCheckExistence::forLocation($path, $e);
        }
    }

    public function directoryExists(string $path): bool
    {
        /* There are no directories, only ids that happen to contain slashes. */
        return true;
    }

    public function write(string $path, string $contents, Config $config): void
    {
        $this->store($path, $contents);
    }

    public function writeStream(string $path, $contents, Config $config): void
    {
        $this->store($path, $contents);
    }

    public function read(string $path): string
    {
        try {
            return $this->images->download($path);
        } catch (Throwable $e) {
            throw UnableToReadFile::fromLocation($path, $e->getMessage(), $e);
        }
    }

    public function readStream(string $path)
    {
        $stream = fopen('php://temp', 'w+b');
        fwrite($stream, $this->read($path));
        rewind($stream);

        return $stream;
    }

    public function delete(string $path): void
    {
        try {
            $this->images->delete($path);
        } catch (Throwable $e) {
            throw UnableToDeleteFile::atLocation($path, $e->getMessage(), $e);
        }
    }

    public function deleteDirectory(string $path): void
    {
        /* Nothing to remove: a directory is only a prefix on ids. */
    }

    public function createDirectory(string $path, Config $config): void
    {
        /* Nothing to create, for the same reason. */
    }

    public function setVisibility(string $path, string $visibility): void
    {
        if ($visibility !== 'public') {
            throw UnableToSetVisibility::atLocation($path, 'Cloudflare Images serves every image publicly.');
        }
    }

    public function visibility(string $path): FileAttributes
    {
        return new FileAttributes($path, visibility: 'public');
    }

    public function mimeType(string $path): FileAttributes
    {
        $type = match (strtolower(pathinfo($path, PATHINFO_EXTENSION))) {
            'jpg', 'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            'gif' => 'image/gif',
            'webp' => 'image/webp',
            'svg' => 'image/svg+xml',
            'avif' => 'image/avif',
            default => null,
        };

        if ($type === null) {
            throw UnableToRetrieveMetadata::mimeType($path, 'No type can be told from the name.');
        }

        return new FileAttributes($path, mimeType: $type);
    }

    public function lastModified(string $path): FileAttributes
    {
        throw UnableToRetrieveMetadata::lastModified($path, 'Cloudflare Images does not report it.');
    }

    public function fileSize(string $path): FileAttributes
    {
        throw UnableToRetrieveMetadata::fileSize($path, 'Cloudflare Images does not report it.');
    }

    public function listContents(string $path, bool $deep): iterable
    {
        return [];
    }

    public function move(string $source, string $destination, Config $config): void
    {
        try {
            $this->copy($source, $destination, $config);
            $this->delete($source);
        } catch (Throwable $e) {
            throw UnableToMoveFile::fromLocationTo($source, $destination, $e);
        }
    }

    public function copy(string $source, string $destination, Config $config): void
    {
        try {
            $this->store($destination, $this->read($source));
        } catch (Throwable $e) {
            throw UnableToCopyFile::fromLocationTo($source, $destination, $e);
        }
    }

    /**
     * @param  string|resource  $contents
     */
    private function store(string $path, $contents): void
    {
        try {
            /* An id can be used once; writing over a path means replacing the image. */
            if ($this->images->exists($path)) {
                $this->images->delete($path);
            }

            $bytes = is_resource($contents) ? stream_get_contents($contents) : $contents;

            $this->images->upload(ColorProfile::toSrgb($bytes), $path);
        } catch (Throwable $e) {
            throw UnableToWriteFile::atLocation($path, $e->getMessage(), $e);
        }
    }
}
