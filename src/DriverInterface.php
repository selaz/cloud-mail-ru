<?php

namespace Selaz\Cloud;

use Selaz\File;

interface DriverInterface {
    public function __construct(string $login, string $password, $keyCacheFile = null);

    public function ls(string $path): array;

    public function remove(CloudFile $path): bool;

    public function upload(File $file, CloudFile $path): CloudFile;

    public function download(CloudFile $path, File $file): bool;

    public function moveToFolder(CloudFile $from, CloudFolder $to): CloudFile;
	
	public function rename(CloudFile $from, string $newName): CloudFile;
	
    public function copy(CloudFile $from, CloudFile $to): CloudFile;
	
	public function mkdir(CloudFolder $dir): CloudFolder;
}