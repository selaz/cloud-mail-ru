<?php

namespace Selaz;

class File {

	private $path;

	public function __construct(string $path) {
		$this->path = $path;
	}

	/**
	 * @return string
	 */
	public function __toString() {
		return $this->path;
	}

	public function getName(): string {
		return basename($this->path);
	}

	public function getSize() {
		return filesize($this->path);
	}

	public function getResource(string $mode = 'r') {
		return fopen($this->path, $mode);
	}

	/**
	 * touch or create file
	 * @param int|null $time
	 * @param int|null $atime
	 * @return bool
	 */
	public function touch(?int $time = null, ?int $atime = null): bool {
		$time = $time ?? time();
		$atime = $atime ?? time();
		return touch($this->path, $time, $atime);
	}

	/**
	 * 
	 * @return true
	 * @throws FileException
	 */
	public function create(): bool {
		if (!$this->touch()) {
			throw new FileException('Can`t create file', 3);
		}

		return true;
	}

	/**
	 * return true id file exist
	 * 
	 * @return bool
	 */
	public function exist(): bool {
		return is_file($this->path);
	}

	/**
	 * Return true if file is writable
	 * 
	 * @return bool
	 */
	public function writable(): bool {
		return is_writable($this->path);
	}

	/**
	 * put data into file
	 * 
	 * @param mixed $content
	 * @param bool $append
	 * @param bool $lock
	 * @return int|false
	 * @throws FileException
	 */
	public function put($content, bool $append = false, bool $lock = false) {
		$flags = ($append) ? FILE_APPEND : 0;
		$flags = ($lock) ? $flags | LOCK_EX : $flags;

		if (!$this->exist()) {
			$this->create();
		}

		if (!$this->writable()) {
			throw new FileException('File isn`t writable', 2);
		}

		return file_put_contents($this->path, $content, $flags);
	}

	/**
	 * 
	 * @param int $offset
	 * @param int $len
	 * @return string|false
	 */
	public function get(int $offset = 0, ?int $len = null) {

		if (!$this->exist()) {
			throw new FileException('File not exists', 1);
		}

		if ($len) {
			return file_get_contents($this->path, false, null, $offset, $len);
		} else {
			return file_get_contents($this->path, false, null, $offset);
		}
	}

}
