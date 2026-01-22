<?php

namespace Tigress;

use stdClass;

/**
 * Class SetupRepository (PHP version 8.5)
 *
 * @author Rudy Mas <rudy.mas@rudymas.be>
 * @copyright 2025-2026, rudymas.be. (http://www.rudymas.be/)
 * @license https://opensource.org/licenses/GPL-3.0 GNU General Public License, version 3 (GPL-3.0)
 * @version 2026.01.22.0
 * @package Tigress\SetupRepository
 */
class SetupRepository extends Repository
{
    private StdClass $data;

    /**
     * Get the version of the Repository
     *
     * @return string
     */
    public static function version(): string
    {
        return '2026.01.22';
    }

    public function __construct()
    {
        $this->createTable = [
            'table' => "
                CREATE TABLE `{$this->table}` (
                  `setting` varchar(30) NOT NULL,
                  `value` text NOT NULL
                ) ENGINE=InnoDB
                  DEFAULT CHARSET=utf8mb4
                  COLLATE=utf8mb4_general_ci
                  ROW_FORMAT=DYNAMIC;
            ",
            'indexes' => [
                "ALTER TABLE `{$this->table}` ADD UNIQUE KEY `instelling` (`setting`) USING BTREE;"
            ],
            'seed' => [
                "INSERT INTO `{$this->table}` (`setting`, `value`)
                 VALUES ('access_settings', '[1]')
                 ON DUPLICATE KEY UPDATE `value` = `value`;"
            ]
        ];

        parent::__construct();
    }

    /**
     * Get a setting
     *
     * @param string $setting
     * @return string
     */
    public function _get(string $setting): string
    {
        return $this->data->{$setting} ?? '';
    }

    /**
     * Get all settings
     *
     * @return stdClass
     */
    public function _getAll(): stdClass
    {
        return $this->data;
    }

    /**
     * Check if user has access
     *
     * @param string $field
     * @param int $value
     * @return bool
     */
    public function _hasAccess(string $field, int $value): bool
    {
        return in_array($value, json_decode($this->data->{$field}, true));
    }

    /**
     * Load all data from the database
     *
     * @param string $orderBy
     * @return void
     */
    public function _loadAll(string $orderBy = ''): void
    {
        $this->loadAll($orderBy);

        $this->data = new StdClass();
        foreach ($this as $object) {
            $this->data->{$object->setting} = $object->value;
        }
    }

    /**
     * Save all data to the database
     *
     * @return void
     */
    public function _saveAll(): void
    {
        $this->reset();
        foreach ($this->data as $setting => $value) {
            $this->loadByPrimaryKey(['setting' => $setting]);
            $row = $this->current();
            $row->setting = $setting;
            $row->value = $value;
            $this->save($row);
        }
    }

    /**
     * Set a setting & save it
     *
     * @param string $setting
     * @param string $value
     * @return void
     */
    public function _set(string $setting, string $value): void
    {
        $this->data->{$setting} = $value;
        $this->_saveAll();
    }

    /**
     * Update a setting
     *
     * @param string $setting
     * @param string $value
     * @return void
     */
    public function _update(string $setting, string $value): void
    {
        $this->data->{$setting} = $value;
    }
}