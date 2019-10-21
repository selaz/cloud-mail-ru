<?php

namespace Selaz\Cloud;

use GuzzleHttp\Client,
	GuzzleHttp\Cookie\FileCookieJar,
	GuzzleHttp\Psr7\MultipartStream,
	GuzzleHttp\Psr7\Request,
	GuzzleHttp\RequestOptions,
	InvalidArgumentException,
	Selaz\Cloud\CloudFile,
	Selaz\Cloud\CloudFolder,
	Selaz\Exceptions\KeyException,
	Selaz\File,
	Selaz\Logs\LoggerTrait;
use function GuzzleHttp\json_decode,
			 GuzzleHttp\json_encode;

class MailRu implements DriverInterface {

	use LoggerTrait;

	private $key;
	private $guzzle;
	private $keyCacheFile = '/tmp/mail-ru-cloud-key';
	private $keyCookieFile = '/tmp/mail-ru-cloud-cookie';

	const BASE_URL = 'https://cloud.mail.ru/api/v2';

	public function __construct(string $login, string $password, $cacheDir = null) {
		$this->keyCacheFile = ($cacheDir) ? sprintf('%s/mail-ru-cloud-key', rtrim($cacheDir, '/')) : $this->keyCacheFile;
		$this->keyCookieFile = ($cacheDir) ? sprintf('%s/mail-ru-cloud-cookie', rtrim($cacheDir, '/')) : $this->keyCookieFile;

		$this->guzzle = new Client([
			'headers'	 => [
				'Accept'	 => '*/*',
				'User-Agent' => 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/77.0.3865.120 Safari/537.36'
			],
			'cookies'	 => new FileCookieJar($this->keyCookieFile, true)
		]);

		$this->key = $this->getToken($login, $password);
		$this->dispatcher();
		$this->key = Key::loadFromFile(new File($this->keyCacheFile)); //reload after dispathcer
	}

	private function getToken(string $login, string $password): Key {
		$keyFile = new File($this->keyCacheFile);

		if ($keyFile->exist()) {
			try {
				$key = Key::loadFromFile($keyFile);
				$this->debug('Key loaded from file');
			} catch (KeyException $e) {
				$this->debug('Key loaded file expired');
				$key = Key::loadFromFile($this->auth($login, $password));
			}
		} else {
			$key = Key::loadFromFile($this->auth($login, $password));
		}

		return $key;
	}

	private function auth(string $login, string $password): File {
		$this->query('POST', 'https://auth.mail.ru/cgi-bin/auth', [
			'Login'		 => $login,
			'Password'	 => $password,
			'Domain'	 => 'mail.ru',
			], false);

		$this->query('GET', 'https://cloud.mail.ru', [], false);

		$token = $this->query('GET', 'https://cloud.mail.ru/api/v2/tokens/csrf', [
			'api'		 => 'v2',
			'email'		 => $login,
			'x-email'	 => $login
			], false);

		$tokenData = [
			'token'		 => $token['body']['token'],
			'deadline'	 => time() + 600,
			'login'		 => $token['email']
		];

		$file = new File($this->keyCacheFile);
		if (!$file->exist()) {
			$file->create();
		}
		$file->put(json_encode($tokenData));

		return $file;
	}

	private function dispatcher() {
		$data = $this->query('GET', sprintf('%s/dispatcher', self::BASE_URL), [
			'token' => $this->key->getToken()
		]);

		$dispatch = [
			'upload'	 => $data['body']['upload'][0]['url'],
			'download'	 => $data['body']['get'][0]['url']
		];

		$keyFile = new File($this->keyCacheFile);
		$data = json_decode($keyFile->get(), true);
		$data = array_merge($dispatch, $data);
		$keyFile->put(json_encode($data));
	}

	/**
	 * copy file in cloud
	 * 
	 * @param string $from
	 * @param string $to
	 * @return bool
	 */
	public function copy(CloudFile $from, CloudFile $to): CloudFile {
		$answer = $this->query('POST', sprintf('%s/file/copy', self::BASE_URL), [
			'folder'	 => $to->getPath(),
			'conflict'	 => 'rename',
			'home'		 => $from->getPath(),
		]);

		return new CloudFile($answer['body']);
	}

	/**
	 * Create new directory 
	 * 
	 * @param CloudFolder $dir
	 * @return CloudFolder
	 */
	public function mkdir(CloudFolder $dir): CloudFolder {
		$answer = $this->query('POST', sprintf('%s/folder/add', self::BASE_URL), [
			'home' => $dir->getPath(),
		]);

		return new CloudFolder($answer['body']);
	}

	/**
	 * Download file crom cloud
	 * 
	 * @param CloudFile $dfile
	 * @param File $file
	 * @return bool
	 */
	public function download(CloudFile $dfile, File $file): bool {
		$url = sprintf("%s%s", $this->key->get('download'), $dfile->getPath());

		$result = $this->guzzle->request('GET', $url, [RequestOptions::SINK => $file->__toString()]);

		return $result->getStatusCode() === 200;
	}

	/**
	 * Upload file to cloud
	 * 
	 * @param File $file
	 * @param CloudFile $path
	 * @return CloudFile
	 */
	public function upload(File $file, CloudFile $path): CloudFile {

		$ms = new MultipartStream([[
			'name'		 => 'file',
			'contents'	 => $file->getResource(),
		]]);

		$params = [
			'query'		 => [
				'cloud_domain'	 => 2,
				'x-email'		 => $this->key->getLogin(),
			],
			'body'		 => $ms,
			'headers'	 => [
				'Content-Disposition'	 => sprintf('form-data; name="file"; filename="%s"', $file->getName()),
				'Content-Type'			 => sprintf('multipart/form-data; boundary=%s', $ms->getBoundary()),
			]
		];


		$resource = $this->guzzle->request('POST', $this->key->get('upload'), $params);

		$hash = strstr($resource->getBody()->getContents(), ';', true);

		$answer = $this->query('POST', sprintf('%s/file/add', self::BASE_URL), [
			'home'		 => $path->getPath(),
			'hash'		 => $hash,
			'size'		 => $file->getSize(),
			'conflict'	 => 'rename'
		]);

		return new CloudFile($answer['body']);
	}

	/**
	 * Show file&folder list in dir
	 * return CloudFile & CloudFolder objects array
	 * 
	 * @param string $path
	 * @return array
	 */
	public function ls(string $path): array {
		$data = $this->query('GET', sprintf('%s/folder', self::BASE_URL), [
			'home'	 => $path,
			'sort'	 => '{"type":"name","order":"asc"}'
		]);

		$list = [];
		foreach ($data['body']['list'] as $item) {
			switch ($item['type']) {
				case "file":
					$object = new CloudFile($item['home']);
					break;
				case "folder":
					$object = new CloudFolder($item['home']);
					break;
				default:
					$this->warning("Unknown item type", $item);
					continue 2;
			}

			$list[] = $object;
		}

		return $list;
	}

	/**
	 * rename cloud file
	 * 
	 * @param CloudFile $from
	 * @param string $newName
	 * @return CloudFile
	 */
	public function rename(CloudFile $from, string $newName): CloudFile {
		$answer = $this->query('POST', sprintf('%s/file/rename', self::BASE_URL), [
			'name'	 => $newName,
			'home'	 => $from->getPath(),
		]);

		return new CloudFile($answer['body']);
	}

	/**
	 * Move cloud file to cloud folder
	 * 
	 * @param CloudFile $from
	 * @param CloudFolder $to
	 * @return CloudFile
	 */
	public function moveToFolder(CloudFile $from, CloudFolder $to): CloudFile {
		$answer = $this->query('POST', sprintf('%s/file/move', self::BASE_URL), [
			'folder'	 => $to->getPath(),
			'conflict'	 => 'rename',
			'home'		 => $from->getPath(),
		]);

		return new CloudFile($answer['body']);
	}

	/**
	 * Remove file (or folder) from cloud
	 * @param string $path
	 * @return bool
	 */
	public function remove(CloudFile $path): bool {
		$this->query('POST', sprintf('%s/file/remove', self::BASE_URL), ['home' => $path->getPath()]);

		return true;
	}

	private function query(string $method, string $url, array $params = [], bool $defaults = true) {

		$options = [
			RequestOptions::CONNECT_TIMEOUT	 => 10,
			RequestOptions::TIMEOUT			 => 10,
			RequestOptions::HTTP_ERRORS		 => false
		];

		if ($defaults) {
			$params = array_merge([
				'home'		 => null,
				'api'		 => 'v2',
				'email'		 => $this->key->getLogin(),
				'x-email'	 => $this->key->getLogin(),
				'token'		 => $this->key->getToken(),
				'_'			 => $this->key->getDeadline(),
				], $params);
		}

		if ($method == 'GET' && !empty($params)) {
			$url = sprintf('%s?%s', $url, http_build_query($params));
		} elseif ($method == 'POST' && !empty($params)) {
			$options[RequestOptions::FORM_PARAMS] = $params;
		}

		$request = new Request($method, $url);

		$this->debug(sprintf('>>> [%s] %s', $request->getMethod(), $request->getUri()), $params);

		$responce = $this->guzzle->send($request, $options);
		$answerBody = $responce->getBody()->getContents();
		try {
			$answerData = json_decode($answerBody, true);

			if ($answerData['status'] != '200') {
				$this->auth();
				$this->query($method, $url, $params, $defaults);
			}
		} catch (InvalidArgumentException $e) {
			$this->warning('API anwer isn`t json');
			$answerData = $answerBody;
		}

		$this->debug('<<<', is_array($answerData) ? $answerData : [$answerData]);

		return $answerData;
	}

}
