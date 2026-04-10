<?php

declare(strict_types=1);

namespace OCA\FitTracker\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\DB\Types;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

class Version1001Date20260410000000 extends SimpleMigrationStep {

    public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
        /** @var ISchemaWrapper $schema */
        $schema = $schemaClosure();

        // Sleep sessions table
        if (!$schema->hasTable('fit_tracker_sleep')) {
            $table = $schema->createTable('fit_tracker_sleep');
            $table->addColumn('id', Types::INTEGER, [
                'autoincrement' => true,
                'notnull' => true,
            ]);
            $table->addColumn('user_id', Types::STRING, [
                'notnull' => true,
                'length' => 64,
            ]);
            $table->addColumn('name', Types::STRING, [
                'notnull' => true,
                'length' => 255,
            ]);
            $table->addColumn('start_time', Types::DATETIME, [
                'notnull' => true,
            ]);
            $table->addColumn('end_time', Types::DATETIME, [
                'notnull' => true,
            ]);
            $table->addColumn('duration', Types::INTEGER, [
                'notnull' => true,
                'comment' => 'seconds',
            ]);
            $table->addColumn('score', Types::INTEGER, [
                'notnull' => false,
                'default' => null,
                'comment' => '0-100 overall sleep score',
            ]);
            $table->addColumn('hrv_score', Types::INTEGER, [
                'notnull' => false,
                'default' => null,
            ]);
            $table->addColumn('time_deep', Types::INTEGER, [
                'notnull' => false,
                'default' => null,
                'comment' => 'seconds in deep sleep',
            ]);
            $table->addColumn('time_light', Types::INTEGER, [
                'notnull' => false,
                'default' => null,
                'comment' => 'seconds in light sleep',
            ]);
            $table->addColumn('time_rem', Types::INTEGER, [
                'notnull' => false,
                'default' => null,
                'comment' => 'seconds in REM sleep',
            ]);
            $table->addColumn('time_awake', Types::INTEGER, [
                'notnull' => false,
                'default' => null,
                'comment' => 'seconds awake during sleep period',
            ]);
            $table->addColumn('fit_file_path', Types::STRING, [
                'notnull' => true,
                'length' => 1024,
            ]);
            $table->addColumn('created_at', Types::DATETIME, [
                'notnull' => true,
            ]);
            $table->setPrimaryKey(['id']);
            $table->addIndex(['user_id'], 'fit_sleep_uid');
            $table->addIndex(['user_id', 'start_time'], 'fit_sleep_uid_time');
        }

        // Sleep stages table (individual stage transitions)
        if (!$schema->hasTable('fit_tracker_sleep_stages')) {
            $table = $schema->createTable('fit_tracker_sleep_stages');
            $table->addColumn('id', Types::INTEGER, [
                'autoincrement' => true,
                'notnull' => true,
            ]);
            $table->addColumn('sleep_id', Types::INTEGER, [
                'notnull' => true,
            ]);
            $table->addColumn('timestamp', Types::DATETIME, [
                'notnull' => true,
            ]);
            $table->addColumn('stage', Types::STRING, [
                'notnull' => true,
                'length' => 16,
                'comment' => 'awake, light, deep, rem, unmeasurable',
            ]);
            $table->setPrimaryKey(['id'], 'fit_slp_stg_pk');
            $table->addIndex(['sleep_id'], 'fit_slp_stg_sid');
        }

        return $schema;
    }
}
