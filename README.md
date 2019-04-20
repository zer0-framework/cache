# Cache
Компонент служит для кеширования.

## Конфигурация
|Имя|     Тип|       Описание| Значение по-умолчанию|
|:-------:|:---:|:--------------:|:---------------------:|
|type|string| Тип хранилища |Redis

## Пример использования

```php
$pool = $this->app->factory('CachePool');
$user = $pool->item('user.' . $userId)->setCallback(function (Item $item) use ($userId) {
  $item
  ->expiresAfter(60 * 60 * 24)
  ->set(getUserFromDatabase($userId))
  ->save();
})->get();
```
