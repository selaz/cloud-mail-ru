<?php

namespace Selaz\Cloud; 

class CloudFile extends Entitie {
	private $mtime;
	
	public function getMtime(): ?int {
		return $this->mtime;
	}

	public function setMtime(?int $mtime) {
		$this->mtime = $mtime;
	}


}