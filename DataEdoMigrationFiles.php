<?php

namespace App\Console\Commands\DataEdoMigration;


use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

use App\Console\Commands\DataEdoMigration\Traits\DataedoTrait;

/**
 * Make sure you have the following requirements setup
 *
 * Primary key must always be called: id
 * Foreign key always must be called: <table>_id
 *
 * So example, foreig_table = users, then foreign key is user_id
 *
 * Keep format the same as Eloquent/Laravel
 */
class DataEdoMigrationFiles extends Command
{
    use DataedoTrait;
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'make:migration:dataedo';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $startTime = Carbon::now();
        $file_time = $startTime->format('Y_m_d_his');

        // Collect ERD entities
        $entities = $this->collectEntities();

        $relationCollection = [];

        // Create all tables first
        // Connect constraint in seperate iteration
        foreach ($entities as $entity) {

            // Setup base template
            $template = $this->migrationTemplate();

            // Iterate through pregmatches
            $tags = $this->getTemplateTags($template);

            // Create and replace classname
            $this->replaceClassName($template, $tags->class_name, $entity->name);
            $template = str_replace('{{table_comment}}', $entity->description, $template);

            // Replace tablename
            $this->replaceTableName($template, $tags->schema_table, $entity->name);

            // Add columns to template
            $this->addColumns($template, $entity->columns, $entity);

            // Create base migration files

            $startTime = $startTime->addSeconds(1);
            $file_name =  $startTime->format('Y_m_d_his') . '_create_' . strtolower($entity->name) . '_table.php';

            // Storage::disk('dataedo')->put("Database/{$file_time}/{$file_name}", $template);

            // add constraints
            $this->collectConstraints($relationCollection, $entity);
        }

        // generate constraints for migration template
        $this->generateMigrationTemplate($startTime, $relationCollection, $file_time);
    }
}
