<?php

namespace Tigress;

use Exception;

/**
 * Class DataRepository (PHP version 8.3)
 *
 * @author Rudy Mas <rudy.mas@rudymas.be>
 * @copyright 2024, rudymas.be. (http://www.rudymas.be/)
 * @license https://opensource.org/licenses/GPL-3.0 GNU General Public License, version 3 (GPL-3.0)
 * @version 1.0.0
 * @lastmodified 2024-11-15
 * @package Tigress\Repository
 */
class DataRepository extends Repository
{
    private object $data;

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
     * Load the data from the repository
     *
     * @return void
     */
    public function load(): void
    {
        $this->createObjects();
    }

    /**
     * Load all data from the database
     *
     * @param string $orderBy
     * @return void
     * @throws Exception
     */
    public function loadAll(string $orderBy = ''): void
    {
        throw new Exception('Object cannot be loaded like this in the repository');
    }

    /**
     * Load all data from the database of active records
     *
     * @param string $orderBy
     * @return void
     * @throws Exception
     */
    public function loadAllActive(string $orderBy = ''): void
    {
        throw new Exception('Object cannot be loaded like this in the repository');
    }

    /**
     * Load all data from the database of inactive records
     *
     * @param string $orderBy
     * @return void
     * @throws Exception
     */
    public function loadAllInactive(string $orderBy = ''): void
    {
        throw new Exception('Object cannot be loaded like this in the repository');
    }

    /**
     * Load data from the database based on the id
     *
     * @param int $id
     * @param string $orderBy
     * @return void
     * @throws Exception
     */
    public function loadById(int $id, string $orderBy = ''): void
    {
        throw new Exception('Object cannot be loaded like this in the repository');
    }

    /**
     * Load data from the database based on the primary keys
     *
     * @param array $primaryKeyValue
     * @param string $orderBy
     * @return void
     * @throws Exception
     */
    public function loadByPrimaryKey(array $primaryKeyValue, string $orderBy = ''): void
    {
        throw new Exception('Object cannot be loaded like this in the repository');
    }

    /**
     * Load data from the database based on the where clause
     *
     * @param array $where
     * @param string $orderBy
     * @return void
     * @throws Exception
     */
    public function loadByWhere(array $where, string $orderBy = ''): void
    {
        throw new Exception('Object cannot be loaded like this in the repository');
    }

    /**
     * Load data from the database based on the query
     *
     * @param string $sql
     * @param array $keyBindings
     * @return void
     * @throws Exception
     */
    public function loadByQuery(string $sql, array $keyBindings = []): void
    {
        throw new Exception('Object cannot be loaded like this in the repository');
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
     * Save the object at the current position
     *
     * @param object $object
     * @return void
     * @throws Exception
     */
    public function save(object &$object): void
    {
        throw new Exception('Object cannot be saved in this repository');
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
     * @throws Exception
     */
    public function saveAll(): void
    {
        throw new Exception('Objects cannot be saved in this repository');
    }

    /**
     * Delete object by id in the database
     *
     * @param int $id
     * @param string $message
     * @return void
     * @throws Exception
     */
    public function deleteById(int $id, string $message = ''): void
    {
        throw new Exception('Object cannot be deleted in this repository');
    }

    /**
     * Undelete object by id in the database
     *
     * @param int $id
     * @return void
     */
    public function undeleteById(int $id): void
    {
        throw new Exception('Object cannot be undeleted in this repository');
    }

    /**
     * Delete object by primary key in the database
     *
     * @param array $primaryKeyValue
     * @param string $message
     * @return void
     * @throws Exception
     */
    public function deleteByPrimaryKey(array $primaryKeyValue, string $message = ''): void
    {
        throw new Exception('Object cannot be deleted in this repository');
    }

    /**
     * Undelete object by primary key in the database
     *
     * @param array $primaryKeyValue
     * @return void
     * @throws Exception
     */
    public function undeleteByPrimaryKey(array $primaryKeyValue): void
    {
        throw new Exception('Object cannot be undeleted in this repository');
    }

    /**
     * Delete object by where clause in the database
     *
     * @param string $sql
     * @param array $keyBindings
     * @return void
     * @throws Exception
     */
    public function deleteByQuery(string $sql, array $keyBindings = []): void
    {
        throw new Exception('Object cannot be deleted in this repository');
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
        throw new Exception('Table cannot be truncated in this repository');
    }

    /**
     * Get data from the database based on a query
     *
     * @param string $sql
     * @param array $keyBindings
     * @return array
     * @throws Exception
     */
    public function getByQuery(string $sql, array $keyBindings = []): array
    {
        throw new Exception('Object cannot be retrieved in this repository');
    }

    /**
     * Get data from the database based on a query
     *
     * @param string $sql
     * @param array $keyBindings
     * @return mixed
     * @throws Exception
     */
    public function getRowByQuery(string $sql, array $keyBindings = []): mixed
    {
        throw new Exception('Object cannot be retrieved in this repository');
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
     * Create a list of objects
     *
     * @return void
     */
    private function createObjects(): void
    {
        $Model = 'Model\\' . $this->model;

        foreach ($this->data as $row) {
            $newModel = new $Model();
            $newModel->initiateModel($this->fields);
            $newModel->update($row);
            $this->objects[] = $newModel;
        }
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

    /**
     * Set the data
     *
     * @param array $data
     * @return void
     */
    public function setData(array $data): void
    {
        $this->data = (object) $data;
    }
}