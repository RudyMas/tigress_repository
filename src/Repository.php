<?php

namespace Tigress;

/**
 * Class Repository (PHP version 8.3)
 *
 * @author Rudy Mas <rudy.mas@rudymas.be>
 * @copyright 2024, rudymas.be. (http://www.rudymas.be/)
 * @license https://opensource.org/licenses/GPL-3.0 GNU General Public License, version 3 (GPL-3.0)
 * @version 1.5.0
 * @lastmodified 2024-10-08
 * @package Tigress\Repository
 */
class Repository
{
    protected string $model;
    protected Database $database;
    protected string $table;
    protected array $primaryKey;
    private array $fields = [];
    private array $objects = [];

    /**
     * Get the version of the Repository
     *
     * @return string
     */
    public static function version(): string
    {
        return '1.5.0';
    }

    public function __construct(
        Database $database,
        string   $model,
        string   $table,
        array    $primaryKey = ['id'],
        bool     $autoload = false
    )
    {
        $this->database = $database;
        $this->model = $model;
        $this->table = $table;
        $this->primaryKey = $primaryKey;

        if ($autoload) {
            $this->loadTableInformation();
        }
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

                $type = $this->getFieldType($rowType);

                if ($rowNull === 'YES') {
                    $value = 'null';
                } else {
                    if (!empty($rowDefault)) {
                        $value = $rowDefault;
                    } else {
                        if ($type === 'integer') {
                            $value = 0;
                        } elseif ($type === 'float') {
                            $value = 0.0;
                        } elseif ($type === 'datetime') {
                            $value = '0000-00-00 00:00:00';
                        } else {
                            $value = '';
                        }
                    }
                }

                $array = [
                    $rowField => [
                        'value' => $value,
                        'type' => $type
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
}