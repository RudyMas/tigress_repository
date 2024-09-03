<?php

namespace Tigress;

use Exception;

/**
 * Class Repository (PHP version 8.3)
 *
 * @author Rudy Mas <rudy.mas@rudymas.be>
 * @copyright 2024, rudymas.be. (http://www.rudymas.be/)
 * @license https://opensource.org/licenses/GPL-3.0 GNU General Public License, version 3 (GPL-3.0)
 * @version 1.0.0
 * @lastmodified 2024-09-03
 * @package Tigress\Repository
 */
class Repository
{
    private string $model;
    private Database $database;
    private string $table;
    private array $objects = [];

    /**
     * Get the version of the Repository
     *
     * @return string
     */
    public static function version(): string
    {
        return '1.0.0';
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
        $this->database->runQuery($sql);
        $this->setObjectsByQuery();
    }

    public function loadAllActive(string $orderBy = ''): void
    {
        $sql = "SELECT * FROM {$this->table} WHERE active = 1";
        if ($orderBy !== '') {
            $sql .= " ORDER BY {$orderBy}";
        }
        $this->database->runQuery($sql);
        $this->setObjectsByQuery();
    }

    public function loadAllInactive(string $orderBy = ''): void
    {
        $sql = "SELECT * FROM {$this->table} WHERE active = 0";
        if ($orderBy !== '') {
            $sql .= " ORDER BY {$orderBy}";
        }
        $this->database->runQuery($sql);
        $this->setObjectsByQuery();
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
        $sql = "SELECT * FROM {$this->table} WHERE id = :id";
        if ($orderBy !== '') {
            $sql .= " ORDER BY {$orderBy}";
        }
        $keyBindings = [':id' => $id];
        $this->database->runQuery($sql, $keyBindings);
        $this->setObjectsByQuery();
    }

    /**
     * Load data from the database based on a field
     *
     * @param string $field
     * @param mixed $value
     * @param string $orderBy
     * @return void
     */
    public function loadByField(string $field, mixed $value, string $orderBy = ''): void
    {
        $sql = "SELECT * FROM {$this->table} WHERE {$field} = :value";
        if ($orderBy !== '') {
            $sql .= " ORDER BY {$orderBy}";
        }
        $keyBindings = [':value' => $value];
        $this->database->runQuery($sql, $keyBindings);
        $this->setObjectsByQuery();
    }

    /**
     * Load data from the database based on a field and active
     *
     * @param string $field
     * @param mixed $value
     * @param string $orderBy
     * @return void
     */
    public function loadByFieldActive(string $field, mixed $value, string $orderBy = ''): void
    {
        $sql = "SELECT * FROM {$this->table} WHERE {$field} = :value AND active = 1";
        if ($orderBy !== '') {
            $sql .= " ORDER BY {$orderBy}";
        }
        $keyBindings = [':value' => $value];
        $this->database->runQuery($sql, $keyBindings);
        $this->setObjectsByQuery();
    }

    /**
     * Load data from the database based on a field and inactive
     *
     * @param string $field
     * @param mixed $value
     * @param string $orderBy
     * @return void
     */
    public function loadByFieldInactive(string $field, mixed $value, string $orderBy = ''): void
    {
        $sql = "SELECT * FROM {$this->table} WHERE {$field} = :value AND active = 0";
        if ($orderBy !== '') {
            $sql .= " ORDER BY {$orderBy}";
        }
        $keyBindings = [':value' => $value];
        $this->database->runQuery($sql, $keyBindings);
        $this->setObjectsByQuery();
    }

    /**
     * Load data from the database based on a query
     *
     * @param string $sql
     * @param array $keyBindings
     * @return void
     */
    public function loadByQuery(string $sql, array $keyBindings = []): void
    {
        $this->database->runQuery($sql, $keyBindings);
        $this->setObjectsByQuery();
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
            if ($value->id === $object->id) {
                $this->objects[$key] = $object;
                return true;
            }
        }
        return false;
    }

    /**
     * Save an object
     *
     * @param object $object
     * @return bool
     * @throws Exception
     */
    public function save(object $object): bool
    {
        if ($object->id === 0) {
            $result = $this->insertIntoDB($object);
        } elseif ($object->id > 0) {
            $result = $this->updateIntoDB($object);
        } else {
            throw new Exception('Invalid id');
        }
        return $result;
    }

    /**
     * Save all objects
     *
     * @return bool
     * @throws Exception
     */
    public function saveAll(): bool
    {
        $result = true;
        foreach ($this->objects as $object) {
            $result = $this->save($object);
            if (!$result) {
                break;
            }
        }
        return $result;
    }

    /**
     * Delete an object from the database
     *
     * @param int $id
     * @param bool $softDelete
     * @param string $message
     * @return void
     */
    public function delete(int $id, bool $softDelete = false, string $message = ''): void
    {
        $sql = "DELETE FROM {$this->table} WHERE id = :id";
        if ($softDelete) {
            $sql = "UPDATE {$this->table} SET active = 0 WHERE id = :id";
            if ($message !== '') {
                $sql = "UPDATE {$this->table} SET active = 0, message_delete = :message WHERE id = :id";
                $keyBindings[':message'] = $message;
            }
        }
        $keyBindings = [':id' => $id];
        $this->database->runQuery($sql, $keyBindings);
    }

    /**
     * Undelete an object from the database
     *
     * @param int $id
     * @return void
     */
    public function undelete(int $id): void
    {
        $sql = "UPDATE {$this->table} SET active = 1 WHERE id = :id";
        $keyBindings = [':id' => $id];
        $this->database->runQuery($sql, $keyBindings);
    }

    /**
     * Insert an object into the database
     *
     * @param object $object
     * @return bool
     */
    private function insertIntoDB(object $object): bool
    {
        $fields = $values = '';
        foreach ($object as $key => $value) {
            if ($key === 'id') {
                continue;
            }
            $fields .= $key . ', ';
            $values .= ':' . $key . ', ';
            $keyBindings[':' . $key] = $value;
        }
        $fields = rtrim($fields, ', ');
        $values = rtrim($values, ', ');
        $sql = "INSERT INTO {$this->table} ({$fields}) VALUES ({$values})";
        return $this->database->runQuery($sql, $keyBindings);
    }

    /**
     * Update an object in the database
     *
     * @param object $object
     * @return bool
     */
    private function updateIntoDB(object $object): bool
    {
        $fields = '';
        foreach ($object as $key => $value) {
            if ($key === 'id') {
                continue;
            }
            $fields .= $key . ' = :' . $key . ', ';
            $keyBindings[':' . $key] = $value;
        }
        $fields = rtrim($fields, ', ');
        $sql = "UPDATE {$this->table} SET {$fields} WHERE id = :id";
        $keyBindings[':id'] = $object->id;
        return $this->database->runQuery($sql, $keyBindings);
    }

    /**
     * Get all objects
     *
     * @return array
     */
    public function getAllObject(): array
    {
        return $this->objects;
    }

    /**
     * Get first object
     *
     * @return object
     */
    public function getFirstObject(): object
    {
        return $this->objects[0];
    }

    /**
     * Get last object
     *
     * @return object
     */
    public function getLastObject(): object
    {
        return $this->objects[count($this->objects) - 1];
    }

    /**
     * Get object by id
     *
     * @param int $id
     * @return object|false
     */
    public function getObjectById(int $id): object|false
    {
        foreach ($this->objects as $object) {
            if ($object->id === $id) {
                return $object;
            }
        }
        return false;
    }

    /**
     * Get objects by field
     *
     * @param string $field
     * @param mixed $value
     * @return array
     */
    public function getObjectsByField(string $field, mixed $value): array
    {
        $data = [];
        foreach ($this->objects as $object) {
            if ($object->$field === $value) {
                $data[] = $object;
            }
        }
        return $data;
    }

    /**
     * Create a list of objects based on the query
     *
     * @return void
     */
    private function setObjectsByQuery(): void
    {
        $data = $this->database->fetchAll();
        $loadModel = '\\Model\\' . $this->model;
        foreach ($data as $row) {
            $this->objects[] = new $loadModel($row);
        }
    }
}