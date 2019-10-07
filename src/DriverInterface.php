<?php

namespace Selaz\Cloud;

use Selaz\File;

interface DriverInterface {
    public function __construct(Key $key);

    public function lsDir(string $path): array;

    public function remove(string $path): bool;

    public function upload(File $file, string $path): bool;

    public function download(string $path, File $file): bool;

    public function isFile(string $path): bool;

    public function isDir(string $path): bool;

    public function exists(string $path): bool;
}