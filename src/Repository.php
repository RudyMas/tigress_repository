<?php

namespace Tigress;

use Exception;

/**
 * Class Repository (PHP version 8.3)
 *
 * @author Rudy Mas <rudy.mas@rudymas.be>
 * @copyright 2024, rudymas.be. (http://www.rudymas.be/)
 * @license https://opensource.org/licenses/GPL-3.0 GNU General Public License, version 3 (GPL-3.0)
 * @version 1.2.1
 * @lastmodified 2024-10-04
 * @package Tigress\Repository
 */
class Repository
{
    protected string $model;
    protected Database $database;
    protected string $table;
    protected array $primaryKey;
    protected bool $autoload = false;
    private array $objects = [];

    /**
     * Get the version of the Repository
     *
     * @return string
     */
    public static function version(): string
    {
        return '1.2.1';
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
        $this->setObjectsByQuery();
    }

    /**
     * Load all active data from the database
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
        $this->setObjectsByQuery();
    }

    /**
     * Load all inactive data from the database
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
        $sql = "SELECT * FROM {$this->table} WHERE {$this->primaryKey[0]} = :id";
        if ($orderBy !== '') {
            $sql .= " ORDER BY {$orderBy}";
        }
        $keyBindings = [':id' => $id];
        $this->database->selectQuery($sql, $keyBindings);
        $this->setObjectsByQuery();
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
        $count = 0;
        foreach ($this->primaryKey as $key) {
            $sql .= "{$key} = :{$key} AND ";
            $keyBindings[":{$key}"] = $primaryKeyValue[$count];
            $count++;
        }
        $sql = rtrim($sql, ' AND ');
        if ($orderBy !== '') {
            $sql .= " ORDER BY {$orderBy}";
        }
        $this->database->selectQuery($sql, $keyBindings);
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
        $this->database->selectQuery($sql, $keyBindings);
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
        $this->database->selectQuery($sql, $keyBindings);
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
        $this->database->selectQuery($sql, $keyBindings);
        $this->setObjectsByQuery();
    }

    /**
     * Load data from the database based on multiple fields
     *
     * @param array $fields
     * @param string $orderBy
     * @return void
     */
    public function loadByFields(array $fields, string $orderBy = ''): void
    {
        $sql = "SELECT * FROM {$this->table} WHERE ";
        foreach ($fields as $field => $value) {
            $sql .= "{$field} = :{$field} AND ";
            $keyBindings[":{$field}"] = $value;
        }
        $sql = rtrim($sql, ' AND ');
        if ($orderBy !== '') {
            $sql .= " ORDER BY {$orderBy}";
        }
        $this->database->selectQuery($sql, $keyBindings);
        $this->setObjectsByQuery();
    }

    /**
     * Load data from the database based on multiple fields and active
     *
     * @param array $fields
     * @param string $orderBy
     * @return void
     */
    public function loadByFieldsActive(array $fields, string $orderBy = ''): void
    {
        $sql = "SELECT * FROM {$this->table} WHERE ";
        foreach ($fields as $field => $value) {
            $sql .= "{$field} = :{$field} AND ";
            $keyBindings[":{$field}"] = $value;
        }
        $sql .= "active = 1";
        if ($orderBy !== '') {
            $sql .= " ORDER BY {$orderBy}";
        }
        $this->database->selectQuery($sql, $keyBindings);
        $this->setObjectsByQuery();
    }

    /**
     * Load data from the database based on multiple fields and inactive
     *
     * @param array $fields
     * @param string $orderBy
     * @return void
     */
    public function loadByFieldsInactive(array $fields, string $orderBy = ''): void
    {
        $sql = "SELECT * FROM {$this->table} WHERE ";
        foreach ($fields as $field => $value) {
            $sql .= "{$field} = :{$field} AND ";
            $keyBindings[":{$field}"] = $value;
        }
        $sql .= "active = 0";
        if ($orderBy !== '') {
            $sql .= " ORDER BY {$orderBy}";
        }
        $this->database->selectQuery($sql, $keyBindings);
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
        $this->database->selectQuery($sql, $keyBindings);
        $this->setObjectsByQuery();
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
        if (count($this->primaryKey) === 1) {
            $primaryKey = $this->primaryKey[0];
            if ($object->$primaryKey === 0) {
                return $this->insertIntoDB($object);
            } elseif ($object->$primaryKey > 0) {
                return $this->updateIntoDB($object);
            } else {
                throw new Exception('Invalid value for primary key');
            }
        } elseif (count($this->primaryKey) > 1) {
            foreach ($this->objects as $key => $value) {
                $found = true;
                foreach ($this->primaryKey as $primKey) {
                    if ($value->$primKey !== $object->$primKey) {
                        $found = false;
                        break;
                    }
                }
                if ($found) {
                    return $this->updateIntoDB($object);
                }
            }
            return $this->insertIntoDB($object);
        } else {
            throw new Exception('Invalid primary key');
        }
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
     * Save all objects
     *
     * @return bool
     * @throws Exception
     */
    public function saveAll(): bool
    {
        $this->database->beginTransaction();
        foreach ($this->objects as $object) {
            $result = $this->save($object);
            if (!$result) {
                $this->database->rollBack();
                return false;
            }
        }
        $this->database->commit();
        return true;
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
        $this->database->selectQuery($sql, $keyBindings);
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
        $this->database->selectQuery($sql, $keyBindings);
    }

    /**
     * Delete an object from the database based on the primary keys
     *
     * @param array $primaryKeyValue
     * @param bool $softDelete
     * @param string $message
     * @return void
     */
    public function deleteByPrimaryKey(array $primaryKeyValue, bool $softDelete = false, string $message = ''): void
    {
        $sql = "DELETE FROM {$this->table} WHERE ";
        $count = 0;
        foreach ($this->primaryKey as $key) {
            $sql .= "{$key} = :{$key} AND ";
            $keyBindings[":{$key}"] = $primaryKeyValue[$count];
            $count++;
        }
        $sql = rtrim($sql, ' AND ');
        if ($softDelete) {
            $sql = "UPDATE {$this->table} SET active = 0 WHERE ";
            $count = 0;
            foreach ($this->primaryKey as $key) {
                $sql .= "{$key} = :{$key} AND ";
                $keyBindings[":{$key}"] = $primaryKeyValue[$count];
                $count++;
            }
            if ($message !== '') {
                $sql = "UPDATE {$this->table} SET active = 0, message_delete = :message WHERE ";
                $count = 0;
                foreach ($this->primaryKey as $key) {
                    $sql .= "{$key} = :{$key} AND ";
                    $keyBindings[":{$key}"] = $primaryKeyValue[$count];
                    $count++;
                }
                $keyBindings[':message'] = $message;
            }
        }
        $this->database->selectQuery($sql, $keyBindings);
    }

    /**
     * Undelete an object from the database based on the primary keys
     *
     * @param array $primaryKeyValue
     * @return void
     */
    public function undeleteByPrimaryKey(array $primaryKeyValue): void
    {
        $sql = "UPDATE {$this->table} SET active = 1 WHERE ";
        $count = 0;
        foreach ($this->primaryKey as $key) {
            $sql .= "{$key} = :{$key} AND ";
            $keyBindings[":{$key}"] = $primaryKeyValue[$count];
            $count++;
        }
        $sql = rtrim($sql, ' AND ');
        $this->database->selectQuery($sql, $keyBindings);
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
        return $this->database->selectQuery($sql, $keyBindings);
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
        $keyBindings = [];
        foreach ($object as $key => $value) {
            if (in_array($key, $this->primaryKey)) {
                continue;
            }
            $fields .= $key . ' = :' . $key . ', ';
            $keyBindings[':' . $key] = $value;
        }
        $fields = rtrim($fields, ', ');
        $sql = "UPDATE {$this->table} SET {$fields} WHERE ";
        foreach ($this->primaryKey as $key) {
            $sql .= "{$key} = :{$key} AND ";
            $keyBindings[":{$key}"] = $object->$key;
        }
        $sql = rtrim($sql, ' AND ');
        return $this->database->selectQuery($sql, $keyBindings);
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
     * Get object by primary key
     *
     * @param array $primaryKeyValue
     * @return object|false
     */
    public function getObjectByPrimaryKey(array $primaryKeyValue): object|false
    {
        foreach ($this->objects as $object) {
            $found = true;
            foreach ($this->primaryKey as $key) {
                if ($object->$key !== $primaryKeyValue[$key]) {
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
        $newModel = 'Model\\' . $this->model;

        $data = $this->database->fetchAll();
        foreach ($data as $row) {
            if ($this->autoload) {
                $this->objects[] = new $newModel($this->database, $this->table, $row);
            } else {
                $this->objects[] = new $newModel($row);
            }
        }
    }
}