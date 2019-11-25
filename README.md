# cloud-storage-api
This is api client for cloud storages. Now support only cloud.mail.ru

Example usage:
```php
$cloud = new \Selaz\Cloud\MailRu('_LOGIN_', '_PASSWORD_');
var_dump($cloud->ls('/'));
```
