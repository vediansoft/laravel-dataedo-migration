<?php

namespace App\Console\Commands\DataEdoMigration\Traits;

use Illuminate\Support\Facades\Storage;

trait DataedoHelperTrait
{
    // Default laravel tables
    private $laravelDefaultTables = [
        'create_users_table',
        'create_password_resets_table',
        'create_oauth_auth_codes_table',
        'create_oauth_access_tokens_table',
        'create_oauth_refresh_tokens_table',
        'create_oauth_clients_table',
        'create_oauth_personal_access_clients_table',
        'create_failed_jobs_table',
        'create_migrations_table'
    ];

    // dataTypeTable
    private $dataTypeTable = [
        "varchar(255)" => [
            'type' => 'string',
            'length' => 255
        ],
        "varchar" =>  [
            'type' => 'string',
            'length' => 255
        ],
        "varchar(30)" =>  [
            'type' => 'string',
            'length' => 30
        ],
        "float" => [
            'type' => 'float'
        ],
        'double' => [
            'type' => 'float'
        ],
        "int" =>  [
            'type' => 'integer',
        ],
        "datetime" => [
            'type' => 'dateTime',
        ],
        "time" => [
            'type' => 'time',
        ],
        "tinyint(1)" => [
            'type' => 'tinyInteger'
        ],
        "int  " =>  [
            'type' => 'integer',
        ],
        "tinyint" => [
            'type' => 'tinyInteger'
        ],
        "datetime " => [
            'type' => 'dateTime',
        ],
        "char" => [
            'type' => 'string',
            'length' => 255
        ],
        "nvarchar" => [
            'type' => 'string',
            'length' => 255
        ],
        "timestamp" => [
            'type' => 'timestamp'
        ],
        "double" => [
            'type' => 'decimal'
        ],
        "decimal" => [
            'type' => 'decimal'
        ],
        "text" => [
            'type' => 'text'
        ],
        "char(6)" => [
            'type' => 'string',
            'length' => 6
        ],
    ];

    private function addUnique(object $column)
    {
        dump($column->custom_fields);
    }

    private function addForeignKey(string $foreign, string $references, string $on)
    {
        return "    \$table->foreign('{$foreign}')->references('{$references}')->on('{$on}')->onDelete('cascade')->onUpdate('cascade');\r\n";
    }

    private function isColumnWithType(object $column)
    {
        return array_key_exists($column->data_type, $this->dataTypeTable);
    }

    private function addTimestamps()
    {
        return "            \$table->timestamps();\r\n";
    }

    private function addDescription($column, $isUnique = false, $isPrimary = false)
    {
        $result = '';
        // Loose comparisons
        if (!empty($column->description)) {
            $result .= "->comment(\"{$column->description}\")";
        }
        // Decide
        if ($column->is_nullable && !$isPrimary) {
            $result .= '->nullable()';
        }
        // decide
        if ($isUnique && !$isPrimary) {
            $result .= '->unique()';
        }

        return "{$result};\r\n";
    }

    private function createColumnFromType(object $column)
    {
        $data = $this->dataTypeTable[$column->data_type];
        $dataType = $data['type'];
        $dataLength = isset($data['length']) ? $data['length'] : null;
        if (!is_null($dataLength))
            return "            \$table->{$dataType}('{$column->name}', {$dataLength})";
        else
            return "            \$table->{$dataType}('{$column->name}')";
    }

    private function createString(string $name, int $length = null)
    {
        if ($length == null)
            return "            \$table->string('{$name}')";
        else
            return "            \$table->string('{$name}', {$length})";
    }

    private function createForeignKey(string $column)
    {

        return "            \$table->unsignedBigInteger('{$column}')";
    }

    private function createPrimaryKey(string $name)
    {
        return "            \$table->id()";
    }
    private function isTimestamp(string $name)
    {
        return strpos($name, 'created_at') !== FALSE || strpos($name, 'updated_at') !== FALSE || strpos($name, 'deleted_at') !== FALSE;
    }

    private function isPrimaryKey(object $column)
    {
        return $column->name === 'id';
    }

    private function isUuid(object $column)
    {
        return $column->name === 'uuid';
    }

    private function isForeignKey(object $column)
    {
        return strpos($column->name, '_id') !== FALSE && !empty($column->references);
    }


    /**
     * Check for default tables in array
     *
     * @param string $name
     * @return void
     */
    private function searchInArray(string $name)
    {
        return in_array('create_' . $name . '_table', $this->laravelDefaultTables);
    }

    /**
     * Clean description
     *
     * @param string $description
     * @return void
     */
    private function cleanDescription(string &$description)
    {
        $description = preg_replace("/\t/", '', $description);
        $description = preg_replace("/\r\n/", '', $description);
        $description = strip_tags($description, '<style>');
        $description = preg_replace("/<style(.*)\/style>/", '', $description);
    }


    /**
     * Convert array to object
     *
     * @param array $array
     * @return object
     */
    private function toStdObj(array $array)
    {
        return json_decode(json_encode($array, true));
    }

    /**
     * Get the migration schema builder template
     *
     * @return string
     */
    private function migrationTemplate($templatename = 'migration_template')
    {
        return Storage::disk('dataedo')->get("Templates/{$templatename}.ini");
    }
}
