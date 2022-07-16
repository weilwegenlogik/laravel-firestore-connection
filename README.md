# Google Firestore Database Connection to Laravel

[![Latest Version on Packagist](https://img.shields.io/packagist/v/pruvo/laravel-firestore-connection.svg?style=flat-square)](https://packagist.org/packages/pruvo/laravel-firestore-connection)
[![Total Downloads](https://img.shields.io/packagist/dt/pruvo/laravel-firestore-connection.svg?style=flat-square)](https://packagist.org/packages/pruvo/laravel-firestore-connection)
![GitHub Actions](https://github.com/pruvo/laravel-firestore-connection/actions/workflows/main.yml/badge.svg)

This package adds functionalities to the Eloquent model and Query builder for Google Firestore, using the original Laravel API.

## Installation

You can install the package via composer:

```bash
composer require pruvo/laravel-firestore-connection
```

## Configuration
You can use Firestore either as the main database, either as a side database. To do so, add a new firebase connection to config/database.php:
```php
'firestore' => [
    'driver' => 'firestore',
    'database' => \Google\Cloud\Firestore\FirestoreClient::DEFAULT_DATABASE,
    'prefix' => '',

    // The project ID from the Google Developer's Console.
    'projectId' => env('GOOGLE_CLOUD_PROJECT'),

    // The full path to your service account credentials .json file 
    // retrieved from the Google Developers Console.
    'keyFilePath' => env('GOOGLE_APPLICATION_CREDENTIALS'),

    // A hostname and port to emulator service.
    // 'emulatorHost'=> env('FIRESTORE_EMULATOR_HOST', 'localhost:8900'),
],
```

## Eloquent

### Extending the base model
This package includes a Firestore enabled Eloquent class that you can use to define models for corresponding collections.

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Pruvo\LaravelFirestoreConnection\Firestoreable;

class Book extends Model
{
    use HasFactory;
    use Firestoreable;

    public $connection = 'firestore';
    public $table = 'books';
    public $primaryKey = 'id';
    public $keyType = 'string';
    public $perPage = 10;
}
```
## Limitations
Laravel is a framework originally designed to use SQL databases for its ORM. However, Firestore is a NoSQL database, and therefore, it does not support the same features as SQL databases.

From point of view that you are used to SQL databases, the following features are not supported:

### SQL syntaxe
There are not any way to query firestore database using string (like SQL syntaxe) all queries must be done via Firestore SDK.

### Only supports `AND` operator
- SQL database supports `select * from users where status = 'disabled' or age > 18;` but it is not supported on Firestore.

### Do not support equals to `null`
- But there is a workaround: `orderBy('field', 'ASC')->startAt(null)`.

### Do not support relationship because it is impossible cross collection data, so 
- you cannot use `belongsTo`, `hasOne`, `hasMany`, `morphOne`, `morphMany`, `belongsToMany` or `morphToMany`;
- In contrast, Firestore has sub collections, so you can use `hasSubcollection` to define relationships.

### Firestore does not support auto increment. 
- To keep the ID orderable it uses `Str::orderedUuid();` to generate new insert ID and document name.

### Firestore database types
- Avoid use `reference` type becouse it can not be serialized and does not have advantages. Instead use document reference path (string representation of `DocumentReference`).
- Avoid use `map` and `array` types unless it is needed. The `map` is equivalent to associative array, and `array` is equivalent to sequencial array.

### Complexy queries
- It is strogly recommended use [Laravel Scout](https://laravel.com/docs/master/scout) with `pruvo/laravel-firestore-connection`.
- Laravel Scout retrive models by results ids using `whereIn`. Firestore support up to 10 ids by request. So paginate by 10 per page.
- Firebase have an extension that export and sync all data with Google Big Query. BigQuery is SQL like so there you can cross data and build B.I. panels.

## Firestore specific operators

Firestore does not support all SQL-like operators and have some specific operators. [Check the full list](https://googleapis.github.io/google-cloud-php/#/docs/cloud-firestore/v1.19.2/firestore/query?method=where).

### Query builder
```php
DB::table('posts')->where('tags', 'array-contains-any', ['cat', 'dog'])->get();
// or
DB::table('posts')->whereArrayContainsAny('tags', ['cat', 'dog'])->get();
```

### Eloquent builder
```php
Post::where('tags', 'array-contains-any', ['cat', 'dog'])->get();
// or
Post::whereArrayContainsAny('tags', ['cat', 'dog'])->get();
```

## Firestore specific operations

Firestore has specific operations `endAt`, `endBefore`, `limitToLast`, `startAfter` and `startAt`. [Check the full list](https://googleapis.github.io/google-cloud-php/#/docs/cloud-firestore/v1.19.2/firestore/query).

```php
DB::table('user')->orderBy('age', 'ASC')->startAfter([17])->get();
// or
User::orderBy('age', 'ASC')->startAfter([17])->get();
```

### Testing

```bash
composer test
```

### Changelog

Please see [CHANGELOG](CHANGELOG.md) for more information what has changed recently.

## Contributing

Please see [CONTRIBUTING](CONTRIBUTING.md) for details.

### Security

If you discover any security related issues, please email ennio.sousa@pruvo.app instead of using the issue tracker.

## Credits

-   [Ennio Sousa](https://github.com/enniosousa)
-   [All Contributors](../../contributors)

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.