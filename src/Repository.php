<?php

namespace Tigress;

use Iterator;

/**
 * Class Repository (PHP version 8.3)
 *
 * @author Rudy Mas <rudy.mas@rudymas.be>
 * @copyright 2024, rudymas.be. (http://www.rudymas.be/)
 * @license https://opensource.org/licenses/GPL-3.0 GNU General Public License, version 3 (GPL-3.0)
 * @version 1.5.4
 * @lastmodified 2024-10-09
 * @package Tigress\Repository
 */
class Repository implements Iterator
{
    protected string $model;
    protected string $dbName;
    protected string $table;
    protected array $primaryKey;
    protected bool $autoload = false;
    protected bool $softDelete = false;
    private Database $database;
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
        return '1.5.4';
    }

    public function __construct()
    {
        $this->database = DATABASE[$this->dbName];
        if ($this->autoload) {
            $this->loadTableInformation();
        }
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
        $this->createObjects();
    }

    /**
     * Load data from the database based on the where clause
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
     * Save the object
     *
     * @param object $object
     * @return void
     */
    public function save(): void
    {
        $object = $this->current();
        $this->database->beginTransaction();
        if ($this->exists($object)) {
            $this->updateObject($object);
        } else {
            $this->saveObject($object);
        }
        $this->database->commit();
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
     * Delete object by id in the database
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
            if ($message !== '') {
                $sql = "UPDATE {$this->table} SET active = 0, message_delete = :message WHERE id = :id";
                $keyBindings[':message'] = $message;
            }
        }
        $keyBindings = [':id' => $id];
        $this->database->deleteQuery($sql, $keyBindings);
    }

    /**
     * Undelete object by id in the database
     *
     * @param int $id
     * @return void
     */
    public function undeleteById(int $id): void
    {
        $sql = "UPDATE {$this->table} SET active = 1 WHERE id = :id";
        $keyBindings = [':id' => $id];
        $this->database->updateQuery($sql, $keyBindings);
    }

    /**
     * Delete object by primary key in the database
     *
     * @param array $primaryKeyValue
     * @param string $message
     * @return void
     */
    public function deleteByPrimaryKey(array $primaryKeyValue, string $message = ''): void
    {
        $sql = "DELETE FROM {$this->table} WHERE ";
        $count = 0;
        foreach ($this->primaryKey as $key) {
            $sql .= "{$key} = :{$key} AND ";
            $keyBindings[":{$key}"] = $primaryKeyValue[$count];
            $count++;
        }
        $sql = rtrim($sql, ' AND ');
        if ($this->softDelete) {
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
        $sql = rtrim($sql, ' AND ');
        $this->database->deleteQuery($sql, $keyBindings);
    }

    /**
     * Undelete object by primary key in the database
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
        $this->database->updateQuery($sql, $keyBindings);
    }

    /**
     * Delete object by where clause in the database
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
     * Return the model
     *
     * @return mixed
     */
    public function current(): mixed
    {
        return $this->objects[$this->position];
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
     * Return the key of the current object
     *
     * @return mixed
     */
    public function key(): mixed
    {
        return $this->position;
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
     * Rewind the Iterator to the first object
     *
     * @return void
     */
    public function rewind(): void
    {
        $this->position = 0;
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
                if ($object->$key !== $value) {
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
     * Return the number of objects
     *
     * @return int
     */
    public function count(): int
    {
        return count($this->objects);
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
     * Get the field type
     *
     * @param mixed $type
     * @return string
     */
    private function getFieldType(mixed $type): string
    {
        if (preg_match('/int|tinyint|smallint|mediumint|bigint/', $type)) {
            return 'integer';
        } elseif (preg_match('/float|double|decimal/', $type)) {
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
     * Save the object in the database
     *
     * @param object $object
     * @return void
     */
    private function saveObject(object $object): void
    {
        $sql = "INSERT INTO {$this->table} (";
        $values = 'VALUES (';
        foreach ($object as $key => $value) {
            $sql .= "{$key}, ";
            $values .= ":{$key}, ";
            $keyBindings[":{$key}"] = $value;
        }
        $sql = rtrim($sql, ', ') . ') ';
        $values = rtrim($values, ', ') . ')';
        $sql .= $values;
        $this->database->insertQuery($sql, $keyBindings);
    }

    /**
     * Update the object in the database
     *
     * @param object $object
     * @return void
     */
    private function updateObject(object $object): void
    {
        $sql = "UPDATE {$this->table} SET ";
        foreach ($object as $key => $value) {
            $sql .= "{$key} = :{$key}, ";
            $keyBindings[":{$key}"] = $value;
        }
        $sql = rtrim($sql, ', ') . ' WHERE ';
        foreach ($this->primaryKey as $key) {
            $sql .= "{$key} = :{$key} AND ";
            $keyBindings[":{$key}"] = $object->$key;
        }
        $sql = rtrim($sql, ' AND ');
        $this->database->updateQuery($sql, $keyBindings);
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
            $sql .= "{$key} = :{$key} AND ";
            $keyBindings[":{$key}"] = $object->$key;
        }
        $sql = rtrim($sql, ' AND ');
        $this->database->selectQuery($sql, $keyBindings);
        $count = $this->database->fetchCurrent();
        return $count->aantal > 0;
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
     * Return the fields
     *
     * @return array
     */
    public function getFields(): array
    {
        return $this->fields;
    }
}