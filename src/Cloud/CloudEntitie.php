<?php

namespace Selaz\Cloud;

class Entitie {

	private $path;

	public function __construct(?string $path) {
		$this->setPath($path);
	}

	public function getName(): ?string {
		return basename($this->path);
	}

	public function getPath(): ?string {
		return $this->path;
	}

	private function setPath(?string $path) {
		$this->path = $path;
	}

}
