
## Features

1. Log into database and into micro-service
2. Swagger documentation using **[darkaonline/l5-swagger](https://github.com/DarkaOnLine/L5-Swagger)**
3. Service discovery using **[dcarbone/php-consul-api](https://github.com/dcarbone/php-consul-api)**
4. Jwt auth using **[tymon/jwt-auth](https://github.com/tymon/jwt-auth)**
5. SQL to migration using **[bennett-treptow/laravel-migration-generator](https://github.com/bennett-treptow/laravel-migration-generator)**
```composer
composer require bennett-treptow/laravel-migration-generator
```
6. SQL to model using **[reliese/laravel](https://github.com/reliese/laravel)**
```composer
composer require reliese/laravel
```

#### Installation (with Composer)

```composer
composer require nycorp/lite-api
```

## Usage

```shell
php artisan vendor:publish --provider="Nycorp\LiteApi\Providers\LiteApiServiceProvider"
```

### Logging :

#### Enable logger to call your remote logger service or log in app database

the default value is **false**
```dotenv
LOG_REMOTE=false
```

To enable log service in .env
```dotenv
LOG_CHANNEL=stack
LOG_STACK=daily,service_log
LOG_DEPRECATIONS_CHANNEL=null
LOG_LEVEL=debug
```
use the logger provide by Laravel as usual

```php
Log::info("Delete: started {$this->modelLogger($id)} ");
```
