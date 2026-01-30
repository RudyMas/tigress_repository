<?php

namespace Tigress;

use Exception;
use Iterator;
use Throwable;

/**
 * Class Repository (PHP version 8.5)
 *
 * @author Rudy Mas <rudy.mas@rudymas.be>
 * @copyright 2024-2026, rudymas.be. (http://www.rudymas.be/)
 * @license https://opensource.org/licenses/GPL-3.0 GNU General Public License, version 3 (GPL-3.0)
 * @version 2026.01.30.0
 * @package Tigress\Repository
 */
class Repository implements Iterator
{
    protected bool $autoload = false;
    protected array $createTable = [];
    protected Database $database;
    protected ?string $dbName;
    protected string $model;
    protected array $primaryKey;
    protected bool $softDelete = false;
    protected string $table;
    private array $fields = [];
    private array $objects = [];
    private int $position = 0;

    /**
     * Get the version of the Repository
     *
     * @return string
     */
    public static function version(): string
    {
        return '2026.01.30';
    }

    /**
     * @throws Exception|Throwable
     */
    public function __construct()
    {
        if (!is_null($this->dbName)) {
            $this->database = DATABASE[$this->dbName];

            $this->checkIfTableExists();

            if ($this->autoload) {
                $this->loadTableInformation();
            }
        }

        $file = SYSTEM_ROOT . '/translations/translations.json';
        if (file_exists($file)) {
            TRANSLATIONS->load($file);
        }
    }

    /**
     * Check if the table exists, if not create it (race-condition safe),
     * and ensure required indexes exist.
     *
     * Expects $this->createTable to be an array with optional keys:
     * - 'table'   => string  (CREATE TABLE ... )
     * - 'indexes' => string[] (ALTER TABLE ... ADD ... )
     * - 'seed'    => string[] (INSERT ... )
     *
     * If $this->createTable is a simple list of SQL strings, it will still run them
     * but index-checking will be skipped (for backward compatibility).
     *
     * @return void
     * @throws Exception
     * @throws Throwable
     */
    public function checkIfTableExists(): void
    {
        if (empty($this->createTable)) {
            // No auto-create SQL provided
            if (!$this->tableExists($this->table)) {
                throw new Exception("Table {$this->table} does not exist and no create table SQL provided.");
            }
            return;
        }

        // Backward compatible mode: if user still provided a numeric array of SQL strings
        $isLegacyList = array_is_list($this->createTable);

        if ($isLegacyList) {
            // We can only do safe CREATE IF NOT EXISTS here if the provided SQL already uses it.
            // We'll still make it race-safe by swallowing "already exists" errors.
            if (!$this->tableExists($this->table)) {
                foreach ($this->createTable as $sql) {
                    $this->safeDdl($sql);
                }
            }
            return;
        }

        $createSql  = $this->createTable['table']   ?? null;
        $indexSqls  = $this->createTable['indexes'] ?? [];
        $seedSqls   = $this->createTable['seed']    ?? [];

        // 1) Ensure table exists (race-safe)
        if (!$this->tableExists($this->table)) {
            if (empty($createSql)) {
                throw new Exception("Table {$this->table} does not exist and no CREATE TABLE SQL provided.");
            }

            // Make CREATE TABLE race-safe
            $createSql = $this->ensureCreateTableIfNotExists($createSql);
            $this->safeDdl($createSql);
        }

        // 2) Ensure required indexes exist (race-safe)
        // We'll detect index names inside the SQL and check them via information_schema.statistics
        foreach ($indexSqls as $sql) {
            $indexName = $this->extractIndexName($sql);
            if ($indexName === null) {
                // If we can't detect the index name reliably, run it safely (ignore duplicates)
                $this->safeDdl($sql);
                continue;
            }

            if (!$this->indexExists($this->table, $indexName)) {
                $this->safeDdl($sql);
            }
        }

        // 3) Seed data (optional) - make it safe as well
        foreach ($seedSqls as $sql) {
            $this->safeSeed($sql);
        }
    }

    /**
     * Return the number of objects
     *
     * @return int
     */
    public function count(): int
    {
        return count($this->objects);
    }

    /**
     * Return the model
     *
     * @return mixed
     */
    public function current(): mixed
    {
        return $this->objects[$this->position];
    }

    /**
     * Delete an object from the repository
     *
     * @param object $object
     * @return void
     */
    public function delete(object $object): void
    {
        foreach ($this->objects as $key => $value) {
            $found = true;
            foreach ($this->primaryKey as $primKey) {
                if ($value->$primKey !== $object->$primKey) {
                    $found = false;
                    break;
                }
            }
            if ($found) {
                unset($this->objects[$key]);
                break;
            }
        }
    }

    /**
     * Delete an object by field in the database
     *
     * @param string $field
     * @param mixed $value
     * @param string $message
     * @return void
     */
    public function deleteByField(string $field, mixed $value, string $message = ''): void
    {
        $sql = "DELETE FROM {$this->table} WHERE {$field} = :value";
        if ($this->softDelete) {
            $sql = "UPDATE {$this->table} SET active = 0 WHERE {$field} = :value";

            if (key_exists('deleted_user_id', $this->fields)) {
                $sql = "UPDATE {$this->table} SET active = 0, deleted = :deleted, deleted_user_id = :deleted_user_id WHERE {$field} = :value";
                $keyBindings[':deleted'] = date('Y-m-d H:i:s');
                $keyBindings[':deleted_user_id'] = $_SESSION['user']['id'] ?? 0;
            } elseif (key_exists('deleted', $this->fields)) {
                $sql = "UPDATE {$this->table} SET active = 0, deleted = :deleted WHERE {$field} = :value";
                $keyBindings[':deleted'] = date('Y-m-d H:i:s');
            }

            if ($message !== '') {
                $sql = "UPDATE {$this->table} SET active = 0, message_delete = :message WHERE {$field} = :value";

                if (key_exists('deleted_user_id', $this->fields)) {
                    $sql = "UPDATE {$this->table} SET active = 0, message_delete = :message, deleted = :deleted, deleted_user_id = :deleted_user_id WHERE {$field} = :value";
                    $keyBindings[':deleted'] = date('Y-m-d H:i:s');
                    $keyBindings[':deleted_user_id'] = $_SESSION['user']['id'] ?? 0;
                } elseif (key_exists('deleted', $this->fields)) {
                    $sql = "UPDATE {$this->table} SET active = 0, message_delete = :message, deleted = :deleted WHERE {$field} = :value";
                    $keyBindings[':deleted'] = date('Y-m-d H:i:s');
                }
                $keyBindings[':message'] = $message;
            }
        }
        $keyBindings[':value'] = $value;
        $this->database->deleteQuery($sql, $keyBindings);
    }

    /**
     * Delete an object by id in the database
     *
     * @param int $id
     * @param string $message
     * @return void
     */
    public function deleteById(int $id, string $message = ''): void
    {
        $sql = "DELETE FROM {$this->table} WHERE id = :id";
        if ($this->softDelete) {
            $sql = "UPDATE {$this->table} SET active = 0 WHERE id = :id";

            if (key_exists('deleted_user_id', $this->fields)) {
                $sql = "UPDATE {$this->table} SET active = 0, deleted = :deleted, deleted_user_id = :deleted_user_id WHERE id = :id";
                $keyBindings[':deleted'] = date('Y-m-d H:i:s');
                $keyBindings[':deleted_user_id'] = $_SESSION['user']['id'] ?? 0;
            } elseif (key_exists('deleted', $this->fields)) {
                $sql = "UPDATE {$this->table} SET active = 0, deleted = :deleted WHERE id = :id";
                $keyBindings[':deleted'] = date('Y-m-d H:i:s');
            }

            if ($message !== '') {
                $sql = "UPDATE {$this->table} SET active = 0, message_delete = :message WHERE id = :id";

                if (key_exists('deleted_user_id', $this->fields)) {
                    $sql = "UPDATE {$this->table} SET active = 0, message_delete = :message, deleted = :deleted, deleted_user_id = :deleted_user_id WHERE id = :id";
                    $keyBindings[':deleted'] = date('Y-m-d H:i:s');
                    $keyBindings[':deleted_user_id'] = $_SESSION['user']['id'] ?? 0;
                } elseif (key_exists('deleted', $this->fields)) {
                    $sql = "UPDATE {$this->table} SET active = 0, message_delete = :message, deleted = :deleted WHERE id = :id";
                    $keyBindings[':deleted'] = date('Y-m-d H:i:s');
                }
                $keyBindings[':message'] = $message;
            }
        }
        $keyBindings[':id'] = $id;
        $this->database->deleteQuery($sql, $keyBindings);
    }

    /**
     * Delete an object by primary key in the database
     *
     * @param array $primaryKeyValue
     * @param string $message
     * @return void
     */
    public function deleteByPrimaryKey(array $primaryKeyValue, string $message = ''): void
    {
        $sql = "DELETE FROM {$this->table} WHERE ";
        foreach ($this->primaryKey as $key) {
            if (!isset($primaryKeyValue[$key])) {
                continue;
            }
            $sql .= "`{$key}` = :{$key} AND ";
            $keyBindings[":{$key}"] = $primaryKeyValue[$key];
        }
        $sql = rtrim($sql, ' AND ');
        if ($this->softDelete) {
            $sql = "UPDATE {$this->table} SET active = 0 WHERE ";

            if (key_exists('deleted_user_id', $this->fields)) {
                $sql = "UPDATE {$this->table} SET active = 0, deleted = :deleted, deleted_user_id = :deleted_user_id WHERE ";
                $keyBindings[':deleted'] = date('Y-m-d H:i:s');
                $keyBindings[':deleted_user_id'] = $_SESSION['user']['id'] ?? 0;
            } elseif (key_exists('deleted', $this->fields)) {
                $sql = "UPDATE {$this->table} SET active = 0, deleted = :deleted WHERE ";
                $keyBindings[':deleted'] = date('Y-m-d H:i:s');
            }

            foreach ($this->primaryKey as $key) {
                if (!isset($primaryKeyValue[$key])) {
                    continue;
                }
                $sql .= "`{$key}` = :{$key} AND ";
                $keyBindings[":{$key}"] = $primaryKeyValue[$key];
            }
            if ($message !== '') {
                $sql = "UPDATE {$this->table} SET active = 0, message_delete = :message WHERE ";

                if (key_exists('deleted_user_id', $this->fields)) {
                    $sql = "UPDATE {$this->table} SET active = 0, message_delete = :message, deleted = :deleted, deleted_user_id = :deleted_user_id WHERE ";
                    $keyBindings[':deleted'] = date('Y-m-d H:i:s');
                    $keyBindings[':deleted_user_id'] = $_SESSION['user']['id'] ?? 0;
                } elseif (key_exists('deleted', $this->fields)) {
                    $sql = "UPDATE {$this->table} SET active = 0, message_delete = :message, deleted = :deleted WHERE ";
                    $keyBindings[':deleted'] = date('Y-m-d H:i:s');
                }

                foreach ($this->primaryKey as $key) {
                    if (!isset($primaryKeyValue[$key])) {
                        continue;
                    }
                    $sql .= "`{$key}` = :{$key} AND ";
                    $keyBindings[":{$key}"] = $primaryKeyValue[$key];
                }
                $keyBindings[':message'] = $message;
            }
        }

        $sql = rtrim($sql, ' AND ');
        $this->database->deleteQuery($sql, $keyBindings);
    }

    /**
     * Delete an object by where clause in the database
     *
     * @param string $sql
     * @param array $keyBindings
     * @return void
     */
    public function deleteByQuery(string $sql, array $keyBindings = []): void
    {
        $this->database->deleteQuery($sql, $keyBindings);
    }

    /**
     * Find data in the objects
     *
     * @param array $find
     * @return array
     */
    public function find(array $find): array
    {
        $data = [];
        foreach ($this->objects as $object) {
            $found = true;
            foreach ($find as $key => $value) {
                if ($object->$key != $value) {
                    $found = false;
                    break;
                }
            }
            if ($found) {
                $data[] = $object;
            }
        }
        return $data;
    }

    /**
     * Find the first object in the objects
     *
     * @param array $find
     * @return object|bool
     */
    public function findFirst(array $find): object|bool
    {
        foreach ($this->objects as $object) {
            $found = true;
            foreach ($find as $key => $value) {
                if ($object->$key != $value) {
                    $found = false;
                    break;
                }
            }
            if ($found) {
                return $object;
            }
        }
        return false;
    }

    /**
     * Force Delete an object by field in the database
     *
     * @param string $field
     * @param mixed $value
     * @return void
     */
    public function forceDeleteByField(string $field, mixed $value): void
    {
        $sql = "DELETE FROM {$this->table} WHERE {$field} = :value";
        $keyBindings[':value'] = $value;
        $this->database->deleteQuery($sql, $keyBindings);
    }

    /**
     * Force Delete an object by id in the database
     *
     * @param int $id
     * @return void
     */
    public function forceDeleteById(int $id): void
    {
        $sql = "DELETE FROM {$this->table} WHERE id = :id";
        $keyBindings[':id'] = $id;
        $this->database->deleteQuery($sql, $keyBindings);
    }

    /**
     * Get data from the loaded objects
     *
     * @param int $id
     * @return object|bool
     */
    public function get(int $id): object|bool
    {
        foreach ($this as $object) {
            if ($object->id === $id) {
                return $object;
            }
        }
        return false;
    }

    /**
     * Get data from the database based on a query
     *
     * @param string $sql
     * @param array $keyBindings
     * @return array
     */
    public function getByQuery(string $sql, array $keyBindings = []): array
    {
        $this->database->selectQuery($sql, $keyBindings);
        return $this->database->fetchAll();
    }

    /**
     * Return the fields
     *
     * @return array
     */
    public function getFields(): array
    {
        return $this->fields;
    }

    /**
     * Get the list of field values
     *
     * @param string $field
     * @return array
     */
    public function getListOfField(string $field): array
    {
        $list = [];
        foreach ($this->objects as $object) {
            if (isset($object->$field)) {
                $list[] = $object->$field;
            }
        }
        return $list;
    }

    /**
     * Create the options for the select
     *
     * @param mixed $id
     * @param bool|string $text
     * @param string $display
     * @param string $value
     * @param bool $onlyActive
     * @param string $inactiveText
     * @param string $initialValuePlaceholder
     * @return string
     */
    public function getOptions(
        mixed       $id,
        bool|string $text,
        string      $display,
        string      $value = 'id',
        bool        $onlyActive = false,
        string      $inactiveText = ' - Inactive',
        string      $initialValuePlaceholder = ''
    ): string
    {
        $this->loadAll($display);
        return $this->createOptions($id, $text, $display, $value, $onlyActive, $inactiveText, $initialValuePlaceholder);
    }

    /**
     * Get data from the database based on a query
     *
     * @param string $sql
     * @param array $keyBindings
     * @return mixed
     */
    public function getRowByQuery(string $sql, array $keyBindings = []): mixed
    {
        $this->database->selectQuery($sql, $keyBindings);
        return $this->database->fetchCurrent();
    }

    /**
     * Get the table name
     *
     * @return string
     */
    public function getTableName(): string
    {
        return $this->table;
    }

    /**
     * Insert an object in the repository
     *
     * @param object $object
     * @return void
     */
    public function insert(object $object): void
    {
        $this->objects[] = $object;
    }

    /**
     * Check if the objects are empty
     *
     * @return bool
     */
    public function isEmpty(): bool
    {
        return empty($this->objects);
    }

    /**
     * Return the key of the current object
     *
     * @return mixed
     */
    public function key(): mixed
    {
        return $this->position;
    }

    /**
     * Load all data from the database
     *
     * @param string $orderBy
     * @return void
     */
    public function loadAll(string $orderBy = ''): void
    {
        $sql = "SELECT * FROM {$this->table}";
        if ($orderBy !== '') {
            $sql .= " ORDER BY {$orderBy}";
        }
        $this->database->selectQuery($sql);
        $this->createObjects();
    }

    /**
     * Load all data from the database of active records
     *
     * @param string $orderBy
     * @return void
     */
    public function loadAllActive(string $orderBy = ''): void
    {
        $sql = "SELECT * FROM {$this->table} WHERE active = 1";
        if ($orderBy !== '') {
            $sql .= " ORDER BY {$orderBy}";
        }
        $this->database->selectQuery($sql);
        $this->createObjects();
    }

    /**
     * Load all distinct data from the database of active records
     *
     * @param string $distinctField
     * @param string $orderBy
     * @return void
     */
    public function loadAllActiveDistinct(string $distinctField, string $orderBy = ''): void
    {
        $sql = "SELECT DISTINCT {$distinctField} FROM {$this->table} WHERE active = 1";
        if ($orderBy !== '') {
            $sql .= " ORDER BY {$orderBy}";
        }
        $this->database->selectQuery($sql);
        $this->createObjects();
    }

    /**
     * Load all distinct data from the database
     *
     * @param string $distinctField
     * @param string $orderBy
     * @return void
     */
    public function loadAllDistinct(string $distinctField, string $orderBy = ''): void
    {
        $sql = "SELECT DISTINCT {$distinctField} FROM {$this->table}";
        if ($orderBy !== '') {
            $sql .= " ORDER BY {$orderBy}";
        }
        $this->database->selectQuery($sql);
        $this->createObjects();
    }

    /**
     * Load all distinct data from the database based on the where clause
     *
     * @param array $where
     * @param string $distinctField
     * @param string $orderBy
     * @return void
     */
    public function loadAllDistinctByWhere(array $where, string $distinctField, string $orderBy = ''): void
    {
        $sql = "SELECT DISTINCT {$distinctField} FROM {$this->table} WHERE ";
        foreach ($where as $key => $value) {
            $sql .= "{$key} = :{$key} AND ";
            $keyBindings[":{$key}"] = $value;
        }
        $sql = rtrim($sql, ' AND ');
        if ($orderBy !== '') {
            $sql .= " ORDER BY {$orderBy}";
        }
        $this->database->selectQuery($sql, $keyBindings);
        $this->createObjects();
    }

    /**
     * Load all data from the database of inactive records
     *
     * @param string $orderBy
     * @return void
     */
    public function loadAllInactive(string $orderBy = ''): void
    {
        $sql = "SELECT * FROM {$this->table} WHERE active = 0";
        if ($orderBy !== '') {
            $sql .= " ORDER BY {$orderBy}";
        }
        $this->database->selectQuery($sql);
        $this->createObjects();
    }

    /**
     * Load all distinct data from the database of inactive records
     *
     * @param string $distinctField
     * @param string $orderBy
     * @return void
     */
    public function loadAllInactiveDistinct(string $distinctField, string $orderBy = ''): void
    {
        $sql = "SELECT DISTINCT {$distinctField} FROM {$this->table} WHERE active = 0";
        if ($orderBy !== '') {
            $sql .= " ORDER BY {$orderBy}";
        }
        $this->database->selectQuery($sql);
        $this->createObjects();
    }

    /**
     * Load data from the database based on the id
     *
     * @param int $id
     * @param string $orderBy
     * @return void
     */
    public function loadById(int $id, string $orderBy = ''): void
    {
        $sql = "SELECT * FROM {$this->table} WHERE {$this->primaryKey[0]} = :id";
        if ($orderBy !== '') {
            $sql .= " ORDER BY {$orderBy}";
        }
        $keyBindings = [':id' => $id];
        $this->database->selectQuery($sql, $keyBindings);
        $this->createObjects();
    }

    /**
     * Load data from the database based on the primary keys
     *
     * @param array $primaryKeyValue
     * @param string $orderBy
     * @return void
     */
    public function loadByPrimaryKey(array $primaryKeyValue, string $orderBy = ''): void
    {
        $sql = "SELECT * FROM {$this->table} WHERE ";
        foreach ($this->primaryKey as $key) {
            if (!isset($primaryKeyValue[$key])) {
                continue;
            }
            $sql .= "{$key} = :{$key} AND ";
            $keyBindings[":{$key}"] = $primaryKeyValue[$key];
        }
        $sql = rtrim($sql, ' AND ');
        if ($orderBy !== '') {
            $sql .= " ORDER BY {$orderBy}";
        }
        $this->database->selectQuery($sql, $keyBindings);
        $this->createObjects();
    }

    /**
     * Load data from the database based on the query
     *
     * @param string $sql
     * @param array $keyBindings
     * @return void
     */
    public function loadByQuery(string $sql, array $keyBindings = []): void
    {
        $this->database->selectQuery($sql, $keyBindings);
        $this->createObjects();
    }

    /**
     * Load data from the database based on the where clause
     * The where clause is an array
     *
     * @param array $where
     * @param string $orderBy
     * @return void
     */
    public function loadByWhere(array $where, string $orderBy = ''): void
    {
        $sql = "SELECT * FROM {$this->table} WHERE ";
        foreach ($where as $key => $value) {
            $sql .= "{$key} = :{$key} AND ";
            $keyBindings[":{$key}"] = $value;
        }
        $sql = rtrim($sql, ' AND ');
        if ($orderBy !== '') {
            $sql .= " ORDER BY {$orderBy}";
        }
        $this->database->selectQuery($sql, $keyBindings);
        $this->createObjects();
    }

    /**
     * Load data from the database based on the where clause
     * The where clause is a string
     *
     * @param string $sqlWhere
     * @param array $keyBindings
     * @param string $orderBy
     * @return void
     */
    public function loadByWhereQuery(string $sqlWhere, array $keyBindings = [], string $orderBy = ''): void
    {
        $sql = "SELECT * FROM {$this->table} WHERE {$sqlWhere}";
        if ($orderBy !== '') {
            $sql .= " ORDER BY {$orderBy}";
        }
        $this->database->selectQuery($sql, $keyBindings);
        $this->createObjects();
    }

    /**
     * Load data from the database based on the query
     *
     * @return void
     */
    public function new(): void
    {
        $Model = 'Model\\' . $this->model;
        $newModel = new $Model();
        $newModel->initiateModel($this->fields);
        $this->position = count($this->objects);
        $this->objects[$this->position] = $newModel;
    }

    /**
     * Move to the next object
     *
     * @return void
     */
    public function next(): void
    {
        $this->position++;
    }

    /**
     * Reset the objects
     *
     * @return void
     */
    public function reset(): void
    {
        $this->position = 0;
        $this->objects = [];
    }

    /**
     * Rewind the Iterator to the first object
     *
     * @return void
     */
    public function rewind(): void
    {
        $this->position = 0;
    }

    /**
     * Save the object
     *
     * @param object $object
     * @return void
     */
    public function save(object &$object): void
    {
        $this->database->beginTransaction();
        if ($this->exists($object)) {
            $this->updateObject($object);
        } else {
            $this->saveObject($object);
            if (isset($object->id)) {
                $object->id = (int)$this->database->lastInsertId();
            }
        }
        $this->database->commit();
    }

    /**
     * Save all objects
     *
     * @return void
     */
    public function saveAll(): void
    {
        $this->database->beginTransaction();
        foreach ($this->objects as $object) {
            if ($this->exists($object)) {
                $this->updateObject($object);
            } else {
                $this->saveObject($object);
            }
        }
        $this->database->commit();
    }

    /**
     * Set the fields
     *
     * The fields are the columns of the table
     * The Array is a multidimensional array with the following structure:
     * [
     *     'column_name' => [
     *         'value' => 'default_value',
     *         'type' => 'integer'
     *     ],
     *     'column_name' => [
     *         'value' => 'default_value',
     *         'type' => 'string'
     *     ],
     *     ...
     * ]
     *
     * @param array $fields
     * @return void
     */
    public function setFields(array $fields): void
    {
        $this->fields = $fields;
    }

    /**
     * Return the data as an array
     *
     * @return array
     */
    public function toArray(): array
    {
        $data = [];
        foreach ($this->objects as $object) {
            $data[] = $object->getProperties();
        }

        return $data;
    }

    /**
     * Completely delete the content of the table
     *
     * @param bool $areYouSure
     * @param bool $overruleSoftDelete
     * @return void
     * @throws Exception
     */
    public function truncate(bool $areYouSure = false, bool $overruleSoftDelete = false): void
    {
        if (!$areYouSure) {
            throw new Exception('You must be sure to truncate the table! See documentation for more information.');
        }
        if ($this->softDelete && !$overruleSoftDelete) {
            $sql = "UPDATE {$this->table} SET active = 0";

            if (key_exists('deleted_user_id', $this->fields)) {
                $delete_user_id = $_SESSION['user']['id'] ?? 0;
                $deleted = date('Y-m-d H:i:s');
                $sql = "UPDATE {$this->table} SET active = 0, deleted = '{$deleted}', deleted_user_id = {$delete_user_id}";
            } elseif (key_exists('deleted', $this->fields)) {
                $deleted = date('Y-m-d H:i:s');
                $sql = "UPDATE {$this->table} SET active = 0, deleted = '{$deleted}'";
            }

            $this->database->update($sql);
        } else {
            $sql = "TRUNCATE TABLE {$this->table}";
            $this->database->delete($sql);
        }
    }

    /**
     * Undelete an object by field in the database
     *
     * @param string $field
     * @param mixed $value
     * @return void
     */
    public function undeleteByField(string $field, mixed $value): void
    {
        $sql = "UPDATE {$this->table} SET active = 1 WHERE {$field} = :value";
        $keyBindings = [];

        if (key_exists('deleted_user_id', $this->fields)) {
            $sql = "UPDATE {$this->table} SET active = 1, deleted = :deleted, deleted_user_id = :deleted_user_id WHERE {$field} = :value";
            $keyBindings[':deleted'] = '0000-00-00 00:00:00';
            $keyBindings[':deleted_user_id'] = $_SESSION['user']['id'] ?? 0;
        } elseif (key_exists('deleted', $this->fields)) {
            $sql = "UPDATE {$this->table} SET active = 1, deleted = :deleted WHERE {$field} = :value";
            $keyBindings[':deleted'] = '0000-00-00 00:00:00';
        }

        $keyBindings[':value'] = $value;
        $this->database->updateQuery($sql, $keyBindings);
    }

    /**
     * Undelete an object by id in the database
     *
     * @param int $id
     * @return void
     */
    public function undeleteById(int $id): void
    {
        $sql = "UPDATE {$this->table} SET active = 1 WHERE id = :id";
        $keyBindings = [];

        if (key_exists('deleted_user_id', $this->fields)) {
            $sql = "UPDATE {$this->table} SET active = 1, deleted = :deleted, deleted_user_id = :deleted_user_id WHERE id = :id";
            $keyBindings[':deleted'] = '0000-00-00 00:00:00';
            $keyBindings[':deleted_user_id'] = $_SESSION['user']['id'] ?? 0;
        } elseif (key_exists('deleted', $this->fields)) {
            $sql = "UPDATE {$this->table} SET active = 1, deleted = :deleted WHERE id = :id";
            $keyBindings[':deleted'] = '0000-00-00 00:00:00';
        }

        $keyBindings[':id'] = $id;
        $this->database->updateQuery($sql, $keyBindings);
    }

    /**
     * Undelete an object by primary key in the database
     *
     * @param array $primaryKeyValue
     * @return void
     */
    public function undeleteByPrimaryKey(array $primaryKeyValue): void
    {
        $sql = "UPDATE {$this->table} SET active = 1 WHERE ";

        if (key_exists('deleted_user_id', $this->fields)) {
            $sql = "UPDATE {$this->table} SET active = 1, deleted = :deleted, deleted_user_id = :deleted_user_id WHERE ";
            $keyBindings[':deleted'] = '0000-00-00 00:00:00';
            $keyBindings[':deleted_user_id'] = $_SESSION['user']['id'] ?? 0;
        } elseif (key_exists('deleted', $this->fields)) {
            $sql = "UPDATE {$this->table} SET active = 1, deleted = :deleted WHERE ";
            $keyBindings[':deleted'] = '0000-00-00 00:00:00';
        }

        foreach ($this->primaryKey as $key) {
            if (!isset($primaryKeyValue[$key])) {
                continue;
            }
            $sql .= "`{$key}` = :{$key} AND ";
            $keyBindings[":{$key}"] = $primaryKeyValue[$key];
        }
        $sql = rtrim($sql, ' AND ');
        $this->database->updateQuery($sql, $keyBindings);
    }

    /**
     * Update an object in the repository
     *
     * @param object $object
     * @return bool
     */
    public function update(object $object): bool
    {
        foreach ($this->objects as $key => $value) {
            $found = true;
            foreach ($this->primaryKey as $primKey) {
                if ($value->$primKey !== $object->$primKey) {
                    $found = false;
                    break;
                }
            }
            if ($found) {
                $this->objects[$key] = $object;
                return true;
            }
        }
        return false;
    }

    /**
     * Update an object in the repository by sql query
     *
     * @param string $sql
     * @param array $keyBindings
     * @return void
     */
    public function updateByQuery(string $sql, array $keyBindings = []): void
    {
        $this->database->updateQuery($sql, $keyBindings);
    }

    /**
     * Update the current object in the repository
     *
     * @param object $object
     * @return void
     */
    public function updateCurrent(object $object): void
    {
        foreach ($object as $key => $value) {
            $this->objects[$this->position]->$key = $value;
        }
    }

    /**
     * Check if the current object is valid
     *
     * @return bool
     */
    public function valid(): bool
    {
        return isset($this->objects[$this->position]);
    }

    /**
     * Create the options for the select
     *
     * @param mixed $id
     * @param bool|string $text
     * @param string $display
     * @param string $value
     * @param bool $onlyActive
     * @param string $inactiveText
     * @param string $initialValuePlaceholder
     * @return string
     */
    protected function createOptions(
        mixed       $id,
        bool|string $text,
        string      $display,
        string      $value = 'id',
        bool        $onlyActive = false,
        string      $inactiveText = ' - Inactive',
        string      $initialValuePlaceholder = ''
    ): string
    {
        return $this->createOption($text, $value, $id, $display, $this, $onlyActive, $inactiveText, $initialValuePlaceholder);
    }

    /**
     * Create the options for the select based on data
     *
     * @param mixed $id
     * @param bool|string $text
     * @param string $display
     * @param string $value
     * @param array $data
     * @return string
     */
    protected function createOptionsByData(
        mixed       $id,
        bool|string $text,
        string      $display,
        string      $value = 'id',
        array       $data = []
    ): string
    {
        return $this->createOption($text, $value, $id, $display, $data);
    }

    /**
     * Create a list of objects
     *
     * @return void
     */
    private function createObjects(): void
    {
        $Model = 'Model\\' . $this->model;

        $data = $this->database->fetchAll();
        foreach ($data as $row) {
            $newModel = new $Model();
            $newModel->initiateModel($this->fields);
            $newModel->update($row);
            $this->objects[] = $newModel;
        }
    }

    /**
     * Create the options for the select
     * This method is used to create the options for a select element
     * It can be used with a Repository or an array of data
     *
     * @param bool|string $text
     * @param string $value
     * @param mixed $id
     * @param string $display
     * @param Repository|array $data
     * @param bool $onlyActive
     * @param string $inactiveText
     * @param string $initialValuePlaceholder
     * @return string
     */
    private function createOption(
        bool|string      $text,
        string           $value,
        mixed            $id,
        string           $display,
        Repository|array $data,
        bool             $onlyActive = false,
        string           $inactiveText = ' - Inactive',
        string           $initialValuePlaceholder = ''
    ): string
    {
        $options = (empty($text)) ? '' : "<option value='{$initialValuePlaceholder}'>{$text}</option>";
        foreach ($data as $row) {
            $selected = ($row->$value == $id) ? ' selected' : '';

            $active = !method_exists($row, 'has') || ((!$row->has('active') || $row->active));

            $output = __($row->$display);
            if (!empty($selected) || $active) {
                $options .= "<option value='{$row->$value}'{$selected}>{$output}</option>";
            } elseif (!$onlyActive) {
                $options .= "<option value='{$row->$value}'{$selected}>{$output}{$inactiveText}</option>";
            }
        }

        return $options;
    }

    /**
     * Ensures CREATE TABLE uses IF NOT EXISTS (race-condition safe).
     */
    private function ensureCreateTableIfNotExists(string $createSql): string
    {
        // If it's already present, keep it.
        if (preg_match('/CREATE\s+TABLE\s+IF\s+NOT\s+EXISTS/i', $createSql)) {
            return $createSql;
        }

        // Replace first occurrence of "CREATE TABLE" (case-insensitive) with "CREATE TABLE IF NOT EXISTS"
        return preg_replace('/CREATE\s+TABLE/i', 'CREATE TABLE IF NOT EXISTS', $createSql, 1) ?? $createSql;
    }

    /**
     * Check if the object exists in the database
     *
     * @param object $object
     * @return bool
     */
    private function exists(object $object): bool
    {
        $sql = "SELECT COUNT(*) as aantal FROM {$this->table} WHERE ";
        foreach ($this->primaryKey as $key) {
            $sql .= "`{$key}` = :{$key} AND ";
            $keyBindings[":{$key}"] = $object->$key;
        }
        $sql = rtrim($sql, ' AND ');
        $this->database->selectQuery($sql, $keyBindings);
        $count = $this->database->fetchCurrent();
        return $count->aantal > 0;
    }

    /**
     * Attempts to extract index name from common ALTER TABLE ADD (UNIQUE) KEY statements.
     */
    private function extractIndexName(string $sql): ?string
    {
        // PRIMARY KEY -> indexnaam is altijd 'PRIMARY'
        if (preg_match('/ADD\s+PRIMARY\s+KEY/i', $sql)) {
            return 'PRIMARY';
        }

        // Matches: ADD UNIQUE KEY `name` (...)  or ADD KEY `name` (...) or ADD INDEX `name` (...)
        if (preg_match('/ADD\s+(?:UNIQUE\s+)?(?:KEY|INDEX)\s+`([^`]+)`/i', $sql, $m)) {
            return $m[1];
        }

        // Matches: ADD CONSTRAINT `name` UNIQUE (...)
        if (preg_match('/ADD\s+CONSTRAINT\s+`([^`]+)`\s+UNIQUE/i', $sql, $m)) {
            return $m[1];
        }

        return null;
    }

    /**
     * Get the field type
     *
     * @param mixed $type
     * @return string
     */
    private function getFieldType(mixed $type): string
    {
        if (preg_match('/int|tinyint|smallint|mediumint|bigint/', $type)) {
            return 'integer';
        } elseif (preg_match('/double|decimal/', $type)) {
            return 'double';
        } elseif (str_contains($type, 'float')) {
            return 'float';
        } elseif (preg_match('/varchar|text|char|blob/', $type)) {
            return 'string';
        } elseif (preg_match('/date|time|datetime|timestamp/', $type)) {
            return 'string';
        } else {
            return 'string';
        }
    }

    /**
     * Check index existence using information_schema.statistics.
     */
    private function indexExists(string $table, string $indexName): bool
    {
        $sql = "SELECT 1
                FROM information_schema.statistics
                WHERE table_schema = DATABASE()
                  AND table_name = :table
                  AND index_name = :index
                LIMIT 1";
        $this->database->selectQuery($sql, [':table' => $table, ':index' => $indexName]);
        return $this->database->getRows() > 0;
    }

    /**
     * Load the table information
     *
     * @return void
     */
    private function loadTableInformation(): void
    {
        $sql = "DESCRIBE " . $this->table;
        $this->database->query($sql);

        if ($this->database->getRows() > 0) {
            foreach ($this->database->fetchAll() as $row) {
                $rowField = $row->Field;
                $rowType = $row->Type;
                $rowNull = $row->Null;
                $rowDefault = $row->Default;

                if ($rowNull === 'YES') {
                    $value = 'null';
                } else {
                    if (!empty($rowDefault)) {
                        $value = $rowDefault;
                    } else {
                        if (preg_match('/int|tinyint|smallint|mediumint|bigint/', $rowType)) {
                            $value = 0;
                        } elseif (preg_match('/float|double|decimal/', $rowType)) {
                            $value = 0.0;
                        } elseif ($rowType === 'date') {
                            $value = '0000-00-00';
                        } elseif ($rowType === 'time') {
                            $value = '00:00:00';
                        } elseif (preg_match('/datetime|timestamp/', $rowType)) {
                            $value = '0000-00-00 00:00:00';
                        } else {
                            $value = '';
                        }
                    }
                }

                $array = [
                    $rowField => [
                        'value' => $value,
                        'type' => $this->getFieldType($rowType)
                    ]
                ];

                $this->fields = array_merge($this->fields, $array);
            }
        }
    }

    /**
     * Execute DDL safely: ignore "already exists" type errors that can happen in concurrent requests.
     * @throws Throwable
     */
    private function safeDdl(string $sql): void
    {
        try {
            $this->database->query($sql);
        } catch (Throwable $e) {
            $msg = $e->getMessage();

            if (
                stripos($msg, 'already exists') !== false ||
                stripos($msg, 'Duplicate key name') !== false ||
                stripos($msg, 'Duplicate entry') !== false ||
                stripos($msg, 'Multiple primary key defined') !== false ||
                stripos($msg, 'ER_TABLE_EXISTS_ERROR') !== false ||
                stripos($msg, 'ER_DUP_KEYNAME') !== false ||
                stripos($msg, 'ER_DUP_ENTRY') !== false ||
                stripos($msg, 'ER_MULTIPLE_PRI_KEY') !== false
            ) {
                return;
            }

            throw $e;
        }
    }

    /**
     * Execute seed SQL safely: prefer idempotent inserts.
     * If the SQL isn't idempotent, we swallow duplicate-entry errors.
     * @throws Throwable
     */
    private function safeSeed(string $sql): void
    {
        try {
            $this->database->query($sql);
        } catch (Throwable $e) {
            $msg = $e->getMessage();

            // Duplicate entry / already present → ignore
            if (
                stripos($msg, 'Duplicate entry') !== false ||
                stripos($msg, 'ER_DUP_ENTRY') !== false
            ) {
                return;
            }

            throw $e;
        }
    }

    /**
     * Save the object in the database
     *
     * @param object $object
     * @return void
     */
    private function saveObject(object $object): void
    {
        if (isset($object->created)) {
            $object->created = date('Y-m-d H:i:s');
        }

        if (isset($object->created_user_id)) {
            $object->created_user_id = $_SESSION['user']['id'] ?? 0;
        }

        $sql = "INSERT INTO {$this->table} (";
        $values = 'VALUES (';
        foreach ($object as $key => $value) {
            $sql .= "`{$key}`, ";
            $values .= ":{$key}, ";
            $keyBindings[":{$key}"] = $value;
        }
        $sql = rtrim($sql, ', ') . ') ';
        $values = rtrim($values, ', ') . ')';
        $sql .= $values;
        $this->database->insertQuery($sql, $keyBindings);
    }

    /**
     * Check table existence using information_schema.
     */
    private function tableExists(string $table): bool
    {
        $sql = "SELECT 1
                FROM information_schema.tables
                WHERE table_schema = DATABASE()
                  AND table_name = :table
                LIMIT 1";
        $this->database->selectQuery($sql, [':table' => $table]);
        return $this->database->getRows() > 0;
    }

    /**
     * Update the object in the database
     *
     * @param object $object
     * @return void
     */
    private function updateObject(object $object): void
    {
        if (isset($object->modified)) {
            $object->modified = date('Y-m-d H:i:s');
        }

        if (isset($object->modified_user_id)) {
            $object->modified_user_id = $_SESSION['user']['id'] ?? 0;
        }

        $sql = "UPDATE {$this->table} SET ";
        foreach ($object as $key => $value) {
            $sql .= "`{$key}` = :{$key}, ";
            $keyBindings[":{$key}"] = $value;
        }
        $sql = rtrim($sql, ', ') . ' WHERE ';
        foreach ($this->primaryKey as $key) {
            $sql .= "`{$key}` = :{$key} AND ";
            $keyBindings[":{$key}"] = $object->$key;
        }
        $sql = rtrim($sql, ' AND ');
        $this->database->updateQuery($sql, $keyBindings);
    }
}