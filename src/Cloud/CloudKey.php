<?php

namespace Selaz\Cloud;

use Selaz\Exceptions\KeyException,
	Selaz\File;
use function GuzzleHttp\json_decode;

class Key {

	private $token;
	private $deadline;
	private $login;
	private $data = [];

	public function __construct(string $token) {
		$this->setToken($token);
	}

	public function getToken(): string {
		return $this->token;
	}

	public function setToken(string $token) {
		$this->token = $token;
	}

	/**
	 * token deadline (unixtime)
	 * @return int
	 */
	public function getDeadline(): int {
		return $this->deadline;
	}

	public function getLogin(): ?string {
		return $this->login;
	}

	public function setLogin(?string $login) {
		$this->login = $login;
	}

	/**
	 * token deadline (unixtime)
	 * @param int $deadline
	 */
	public function setDeadline(int $deadline): void {
		if ($deadline < time()) {
			throw new KeyException('Token expired');
		}
		$this->deadline = $deadline;
	}

	public static function loadFromFile(File $file) {
		$data = json_decode($file->get());

		if (empty($data->token)) {
			throw new KeyException('Not valid key file');
		} else {
			$key = new Key($data->token);
		}

		if (!empty($data->deadline)) {
			$key->setDeadline($data->deadline);
		}

		if (!empty($data->login)) {
			$key->setLogin($data->login);
		}

		$key->setData(json_decode($file->get(), true));

		return $key;
	}

	public function get(string $name) {
		return $this->data[$name] ?? null;
	}

	private function setData($data) {
		$this->data = $data;
	}

}
