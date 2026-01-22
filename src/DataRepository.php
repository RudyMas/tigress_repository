<?php

namespace Tigress;

use Exception;

/**
 * Class DataRepository (PHP version 8.5)
 *
 * @author Rudy Mas <rudy.mas@rudymas.be>
 * @copyright 2024-2026, rudymas.be. (http://www.rudymas.be/)
 * @license https://opensource.org/licenses/GPL-3.0 GNU General Public License, version 3 (GPL-3.0)
 * @version 2026.01.22.0
 * @package Tigress\Repository
 */
class DataRepository extends Repository
{
    private array $data;

    /**
     * Get the version of the Repository
     *
     * @return string
     */
    public static function version(): string
    {
        return '2026.01.22';
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
     * Set the data
     *
     * @param array $data
     * @return void
     */
    public function setData(array $data): void
    {
        $this->data = json_decode(json_encode($data));
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
     * Undelete object by id in the database
     *
     * @param int $id
     * @return void
     * @throws Exception
     */
    public function undeleteById(int $id): void
    {
        throw new Exception('Object cannot be undeleted in this repository');
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
     * Create a list of objects
     *
     * @return void
     */
    private function createObjects(): void
    {
        $Model = 'Model\\' . $this->model;

        foreach ($this->data as $row) {
            $newModel = new $Model();
            $newModel->initiateModel(parent::getFields());
            $newModel->update($row);
            $this->insert($newModel);
        }
    }
}