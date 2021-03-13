<?php

namespace App\Console\Commands\DataEdoMigration\Traits;

use Carbon\Carbon;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Storage;

trait DataedoTrait
{
    use DataedoHelperTrait;

    /** ========================================================================================
     *
     *	Add constraints to migration template
     *
     *  ======================================================================================== */
    private function generateMigrationTemplate(Carbon $startTime, array $relationCollection, string $file_time)
    {
        // Empty string for ini template generation
        $migrationTemplate = "";

        // Create relations file
        foreach ($relationCollection as $schemaTable => $constraint) {

            $relationConstraints = "";
            $relationTemplate = $this->migrationTemplate('schema_builder_template');
            $relationTemplate = str_replace('{{schema_table}}', $schemaTable, $relationTemplate);

            foreach ($constraint as $foreignKey => $relation) {
                $relationConstraints .= $this->addForeignKey($foreignKey, $relation['references'], $relation['on']);
            }
            $migrationTemplate .= str_replace('**ENTRY**', $relationConstraints, $relationTemplate);
        }

        $constraintTemplate = $this->migrationTemplate('relation_template');
        $constraintTemplate = str_replace('**ENTRY**', $migrationTemplate, $constraintTemplate);

        $startTime = $startTime->addMinutes(1);
        $file_name = $startTime->format('Y_m_d_his') . '_update_relation_tables.php';

        // Store constraints to template
        // Storage::disk('dataedo')->put("Database/{$file_time}/{$file_name}", $constraintTemplate);
    }
    /** ========================================================================================
     *
     *	Add constraints
     *
     *  ======================================================================================== */
    /**
     * Collects constraint values
     *
     * @param array $collection
     * @param object $entity
     * @return void
     */
    private function collectConstraints(array &$collection, object $entity)
    {
        // Figure out relatable constraints -> they need to be processed seperate
        foreach ($entity->relations as $relation) {
            foreach ($relation->constraints as $constraint) {
                $collection[$relation->foreign_table][$constraint->foreign_column] = [
                    'on' => $relation->primary_table,
                    'references'    => $constraint->primary_column
                ];
            }
        }
    }

    /** ========================================================================================
     *
     *	Add Columns
     *
     *  ======================================================================================== */

    /**
     * Add columns to migration template file
     *
     * Replaces certain values from template file and store them in it.
     *
     * Checks what type of column it is
     *
     *
     * @param string $template
     * @param array $columns
     * @param object $entity
     * @return void
     */
    private function addColumns(string &$template, array $columns, object $entity)
    {
        $uniqueColumns = [];

        // Check for unique keys other than primary
        if (count($entity->unique_keys) > 1) {
            // Unset primary key
            unset($entity->unique_keys[0]);
            // Loop through the other keys
            foreach ($entity->unique_keys as $key) {
                // build the primary name
                $uniqueColumnName = str_replace($entity->name . '_', '', $key->name);
                $uniqueColumnName = str_replace('_unique', '', $uniqueColumnName);
                $uniqueColumnName = str_replace('uk_', '', $uniqueColumnName);
                $uniqueColumns[] = $uniqueColumnName;
            }
        }


        // String to replace columns
        $columnReplacement = "";

        // Check for primary, only set when primary is found
        // otherwise pivot tables will crash
        $primaryKey = false;

        // Add columns to template
        foreach ($columns as $column) {

            $isUnique = false;
            $column->name = trim($column->name);

            // We need to set this individually for each column that is unique
            if (in_array($column->name, $uniqueColumns)) {
                $isUnique = true;
            }

            // Check type of column
            if ($this->isPrimaryKey($column)) {
                // Does not require unique field generation as it already is unique
                $columnReplacement .= $this->createPrimaryKey($column->name);
                $columnReplacement .= $this->addDescription($column, false, true);
                // There is a primary key, so use this for timestamp generation
                $primaryKey = true;
            } elseif ($this->isForeignKey($column)) {
                // skip in this iteration they will be added in modulation file
                $columnReplacement .= $this->createForeignKey($column->name);
                $columnReplacement .= $this->addDescription($column, $isUnique);
            } elseif ($this->isTimestamp(strtolower($column->name))) {
                // do nothing
                // Exempt created_at, updated_at and deleted_at (they will be added differently)
                // (only works for: deleted_at, created_at, updated_at)
            } elseif ($this->isColumnWithType($column)) {
                $columnReplacement .= $this->createColumnFromType($column);
                $columnReplacement .= $this->addDescription($column, $isUnique);
            } else {
                $columnReplacement .= $this->createString($column->name);
                $columnReplacement .= $this->addDescription($column, $isUnique);
            }
        }

        // If there is no primary key, it is a pivot table.
        // we don't use timestamps in pivottables
        if ($primaryKey) {
            $columnReplacement .= $this->addTimestamps();
        }

        // Finally insert column into the template
        $template = str_replace('**ENTRY**', $columnReplacement, $template);
    }


    /**
     * Search and replace schema_table name in template file
     *
     * @param string $template
     * @param string $tag
     * @param string $entity_name
     * @return void
     */
    private function replaceTableName(string &$template, string $tag, string $entity_name)
    {
        $template = str_replace($tag, $entity_name, $template);
    }

    /**
     * Create a classname and replace corresponding tag in migration template
     *
     * @param string $template
     * @param string $tag
     * @param string $entity_name
     * @return void (No return type)
     */
    private function replaceClassName(string &$template, string $tag, string $entity_name)
    {
        // Check whether underscores are found in table name
        if (strpos($entity_name, '_')) {
            $explosion = explode('_', $entity_name);
            $entity_name = '';
            // Iterate through all sections
            foreach ($explosion as $name)
                // Make first letter capitalized
                $entity_name .= ucfirst($name);
        } else {
            // Make first letter capitalized
            $entity_name = ucfirst($entity_name);
        }

        // Replace template tag class_name with entity name
        $template = str_replace($tag, $entity_name, $template);
    }

    /**
     * Get template tags from migration file template
     *
     * @param string $template
     * @return stdObject
     */
    private function getTemplateTags(string $template)
    {
        $templateTags = [];
        preg_match_all('/\{\{(.*)\}\}/', $template, $matches);

        // Loop through indexes and setup tags
        foreach ($matches[1] as $index)
            $templateTags += [
                $index => ''
            ];

        // Combine
        foreach ($matches[0] as $value) {
            $index = ltrim(rtrim($value, '\}\}'), '\{\{');
            $templateTags[$index] = $value;
        }

        // Return tags
        return $this->toStdObj($templateTags);
    }

    /**
     * Collect dataEdo entities
     *
     * @return array
     */
    private function collectEntities()
    {
        $obj = [];
        $disc = Storage::disk('dataedo');

        $storageFiles = $disc->allFiles('data');

        // Loop through data folder
        foreach ($storageFiles as $path) {

            // Skip all files that are not table files
            if (!Str::startsWith($path, 'data/t'))
                continue;

            // Get file from disc and convert to json
            $stream = $disc->get($path);
            $stream = rtrim($stream, ';');
            $stream = str_replace('window.repositoryObject = ', '', $stream);
            $entity = json_decode($stream);

            //skip laravel entites
            if ($this->searchInArray($entity->name))
                continue;

            // When description is not null
            if (!is_null($entity->description))
                // Clean tags append to iteself
                $this->cleanDescription($entity->description);

            // clean columns description
            foreach ($entity->columns as $index => $column)
                if (!is_null($column->description))
                    $this->cleanDescription($column->description);


            // Push entity to array
            $obj[] = $entity;
        }

        return $obj;
    }
}
