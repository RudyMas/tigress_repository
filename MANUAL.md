# Tigress Repository — Programmer's Manual

**Version:** 2026.04.03  
**PHP:** >= 8.5  
**Package:** `tigress/repository`  
**Namespace:** `Tigress\`

---

## 1. Overview

The Tigress Repository library provides a **Data Repository / Data Mapper** pattern over a PDO-based `Database` connection. It abstracts CRUD operations on database tables behind repository classes, with in-memory object caching, Iterator support, soft-delete, and automatic schema management.

Three classes are provided:

| Class | Description |
|---|---|
| `Repository` | Base class — full CRUD against a database table |
| `DataRepository` | Read-only variant for in-memory data (API, JSON, cache) |
| `SetupRepository` | Key-value settings repository |

---

## 2. Prerequisites

The library assumes the following globals are defined by the Tigress Framework:

| Global | Type | Purpose |
|---|---|---|
| `DATABASE` | `array` of `Database` | Connection pool, keyed by name |
| `SYSTEM_ROOT` | `string` | Root path for translations lookup |
| `TRANSLATIONS` | `object` | Translation loader |
| `$_SESSION['user']` | `array` | Current user (for auditing `created_user_id`, `modified_user_id`, `deleted_user_id`) |

If `$dbName` is `null`, the constructor skips database setup entirely (useful for `DataRepository`).

---

## 3. Installation

```bash
composer require tigress/repository
```

---

## 4. Creating a Custom Repository

Subclass `Repository` and set the required properties:

```php
use Tigress\Repository;

class UserRepository extends Repository
{
    protected string $table = 'users';
    protected string $model = 'User';        // Model\User class
    protected ?string $dbName = 'default';    // key into DATABASE[]
    protected array $primaryKey = ['id'];
    protected bool $softDelete = false;
    protected bool $autoload = true;           // auto-load column metadata

    // Optional: auto-create table if it doesn't exist
    protected array $createTable = [
        'table' => "CREATE TABLE `users` (
            `id`         INT AUTO_INCREMENT PRIMARY KEY,
            `name`       VARCHAR(100) NOT NULL,
            `email`      VARCHAR(255) NOT NULL,
            `active`     TINYINT(1) DEFAULT 1,
            `created`    DATETIME,
            `modified`   DATETIME,
            `deleted`    DATETIME
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
        'indexes' => [
            "ALTER TABLE `users` ADD INDEX `idx_email` (`email`)",
        ],
        'seed' => [
            "INSERT INTO `users` (`name`, `email`)
             VALUES ('Admin', 'admin@example.com')
             ON DUPLICATE KEY UPDATE `name` = `name`",
        ],
    ];
}
```

### Properties Reference

| Property | Default | Description |
|---|---|---|
| `$table` | — | Database table name (required) |
| `$model` | — | Model class name (without `Model\` prefix) |
| `$dbName` | `null` | Key into `DATABASE[]` constant (null = no DB) |
| `$primaryKey` | — | Array of primary key column names |
| `$softDelete` | `false` | Enable soft-delete (UPDATE active=0 instead of DELETE) |
| `$autoload` | `false` | Auto-run `DESCRIBE table` on construct to build field metadata |
| `$createTable` | `[]` | DDL/seed arrays for auto table creation |

---

## 5. Loading Data

All `load*` methods **replace** the in-memory collection with fresh data from the database.

```php
$repo = new UserRepository();

$repo->loadAll('name ASC');                      // SELECT * FROM users ORDER BY name ASC
$repo->loadAllActive();                          // WHERE active = 1
$repo->loadAllInactive();                        // WHERE active = 0
$repo->loadAllDistinct('email');                 // SELECT DISTINCT email FROM users
$repo->loadAllActiveDistinct('name');            // SELECT DISTINCT name WHERE active = 1

$repo->loadById(42);                             // WHERE id = 42
$repo->loadByPrimaryKey(['id' => 42]);           // WHERE id = 42 (supports composite keys)
$repo->loadByWhere(['email' => 'a@b.com']);      // WHERE `email` = :email
$repo->loadByWhereQuery("email LIKE '%@b.com'"); // Raw WHERE string
$repo->loadByQuery("SELECT * FROM users WHERE email LIKE :e", [':e' => '%@b.com']);
```

### Read-Only Queries (no in-memory mutation)

```php
$rows = $repo->getByQuery("SELECT id, name FROM users WHERE active = 1");
$row  = $repo->getRowByQuery("SELECT * FROM users WHERE id = :id", [':id' => 42]);
```

---

## 6. Working with Objects

### Iteration (implements `Iterator`)

```php
$repo->loadAll();
foreach ($repo as $user) {
    echo $user->name;
}
```

### Accessing Loaded Objects

```php
$object = $repo->get(42);         // Find by `id` property in loaded collection
$found  = $repo->find(['email' => 'a@b.com']);       // Array of matching objects
$first  = $repo->findFirst(['email' => 'a@b.com']);  // First match or false
$list   = $repo->getListOfField('email');             // Flat array of email values
$count  = $repo->count();         // Number of loaded objects
$isEmpty = $repo->isEmpty();      // Check if collection is empty
$array  = $repo->toArray();       // Export all objects as arrays
$fields = $repo->getFields();     // Column metadata (name => [value, type])
```

### In-Memory Manipulation

```php
$newUser = (object) ['id' => 5, 'name' => 'Jane'];
$repo->insert($newUser);                 // Add to collection (no DB write)
$repo->update($newUser);                 // Replace by matching primary key
$repo->updateCurrent($newUser);          // Replace at current iterator position
$repo->delete($newUser);                 // Remove from collection
$repo->reset();                          // Empty the collection
```

### Creating New Objects

```php
$repo->new();                        // Creates empty Model at current iterator position
$object = $repo->current();
$object->name = 'John';
$object->email = 'john@example.com';
$repo->save($object);                // INSERT (or UPDATE if PK already exists)
```

If the table has a `created` / `created_user_id` column, these are auto-populated on INSERT. On UPDATE, `modified` / `modified_user_id` are auto-populated.

### Saving

```php
$repo->save($object);                // Single save (auto INSERT or UPDATE)
$repo->saveAll();                    // Save all objects in collection (transactional)
```

Both methods wrap themselves in a transaction if one is not already active.

---

## 7. Deleting

### Hard Delete

```php
$repo->deleteByField('email', 'a@b.com');       // DELETE WHERE email = :value
$repo->deleteById(42);                          // DELETE WHERE id = :id
$repo->deleteByPrimaryKey(['id' => 42]);        // DELETE WHERE id = :id
$repo->deleteByWhere(['email' => 'a@b.com']);   // DELETE WHERE `email` = :email
$repo->deleteByQuery("DELETE FROM users WHERE id = :id", [':id' => 42]);
```

### Soft Delete (when `$softDelete = true`)

Calls become `UPDATE table SET active = 0 [ , deleted = NOW(), deleted_user_id = :uid ]`.

Optional delete message (adds `message_delete` column):

```php
$repo->deleteById(42, 'User resigned');
```

### Force Delete (bypasses soft-delete)

```php
$repo->forceDeleteByField('email', 'a@b.com');
$repo->forceDeleteById(42);
```

### Undelete

```php
$repo->undeleteByField('email', 'a@b.com');
$repo->undeleteById(42);
$repo->undeleteByPrimaryKey(['id' => 42]);
$repo->undeleteByWhere(['email' => 'a@b.com']);
```

### Truncate

```php
$repo->truncate(true);              // TRUNCATE TABLE (requires explicit confirmation)
$repo->truncate(true, true);        // Bypass soft-delete, real TRUNCATE
```

Soft-delete + truncate = `UPDATE table SET active = 0` on all rows.

---

## 8. Transactions

```php
$repo->beginTransaction();
try {
    // ... multiple saves/deletes ...
    $repo->commit();
} catch (Throwable $e) {
    $repo->rollBack();
    throw $e;
}
```

`save()` and `saveAll()` auto-wrap themselves in a transaction (nested-safe).

---

## 9. Auto-Table Creation

If `$createTable` is configured and the table doesn't exist, calling any `load*` / `save*` method will:

1. Run `CREATE TABLE IF NOT EXISTS` (race-condition safe)
2. Add any missing indexes (checked via `information_schema.statistics`)
3. Run seed INSERT statements (duplicate errors are swallowed)

The `$createTable` array supports three keys:

```php
protected array $createTable = [
    'table'   => "CREATE TABLE `mytable` ( ... ) ENGINE=InnoDB ...",
    'indexes' => [
        "ALTER TABLE `mytable` ADD UNIQUE KEY `idx_setting` (`setting`)",
    ],
    'seed'    => [
        "INSERT INTO `mytable` (`col`) VALUES ('val') ON DUPLICATE KEY UPDATE `col` = `col`",
    ],
];
```

For backward compatibility, a simple list of SQL strings is also accepted.

---

## 10. HTML Helpers

### Select Dropdown Options

```php
// <select id="user_id">...</select>
echo $repo->getOptions(
    id: $selectedUserId,
    text: '-- Select user --',        // null = no placeholder
    display: 'name',                  // column for option text
    value: 'id',                      // column for option value
    onlyActive: false,                // skip inactive rows
    inactiveText: ' - Inactive',      // suffix for inactive rows
    initialValuePlaceholder: ''       // value attribute for placeholder
);
```

### Yes/No Options

```php
echo $repo->getYesNoOptions(1);             // Yes selected
echo $repo->getYesNoOptions(0, true);       // No first, reversed order
```

---

## 11. DataRepository (Read-Only, In-Memory)

Use when data comes from an API, JSON file, or cache rather than the database.

```php
class MyDataRepo extends DataRepository
{
    protected string $model = 'MyModel';
    // No $table, $dbName, $primaryKey required
}

$repo = new MyDataRepo();
$repo->setData($apiResponse);               // Accepts array or object, converts via json_decode(json_encode())
$repo->load();                              // Creates Model objects from in-memory data

// Now foreach, find, count, toArray, etc. all work
foreach ($repo as $item) {
    echo $item->name;
}
```

All mutation methods (`save`, `deleteById`, `truncate`, etc.) throw `Exception`.

---

## 12. SetupRepository (Key-Value Settings)

A ready-to-use repository for a two-column settings table (`setting` VARCHAR PK, `value` TEXT).

```php
$settings = new SetupRepository();
$settings->_loadAll();

$val = $settings->_get('my_setting');       // Returns '[]' if missing
$all = $settings->_getAll();                // Returns stdClass of all settings

$settings->_set('theme', 'dark');           // Set & persist immediately
$settings->_update('theme', 'light');       // In-memory only, call _saveAll() later
$settings->_saveAll();                      // Persist all in-memory settings

// Access control helper (value is a JSON array of user IDs)
$hasAccess = $settings->_hasAccess('admin_users', $userId, $overrule);
```

The table, index, and seed row for `access_settings` are auto-created on first use.

---

## 13. Utility Methods

```php
$tableName = $repo->getTableName();     // Return the table name
$version   = Repository::version();     // Static version string
$repo->setFields($fieldsArray);         // Override column metadata
$repo->updateByQuery($sql, $bindings);  // Raw SQL UPDATE
```

---

## 14. Complete Example

```php
use Tigress\Repository;

class ProductRepository extends Repository
{
    protected string $table = 'products';
    protected string $model = 'Product';
    protected ?string $dbName = 'default';
    protected array $primaryKey = ['id'];
    protected bool $softDelete = true;
    protected bool $autoload = true;

    protected array $createTable = [
        'table' => "CREATE TABLE `products` (
            `id`       INT AUTO_INCREMENT PRIMARY KEY,
            `name`     VARCHAR(255) NOT NULL,
            `price`    DECIMAL(10,2) NOT NULL DEFAULT 0.00,
            `active`   TINYINT(1) DEFAULT 1,
            `created`  DATETIME,
            `modified` DATETIME,
            `deleted`  DATETIME
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
    ];
}

// --- Usage ---
$repo = new ProductRepository();

// Load all products
$repo->loadAll('name');

// Create a new product
$repo->new();
$product = $repo->current();
$product->name = 'Widget';
$product->price = 9.99;
$repo->save($product);

echo 'New product ID: ' . $product->id;

// Find active products
$repo->loadAllActive();
foreach ($repo as $p) {
    echo $p->name . ' - €' . $p->price;
}

// Soft-delete
$repo->deleteById(1);

// Generate a <select> dropdown
echo '<select name="product">';
echo $repo->getOptions(id: 0, text: '-- Choose --', display: 'name');
echo '</select>';
```
