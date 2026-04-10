<?php

declare(strict_types=1);

namespace OCA\FitTracker\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\DB\Types;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

class Version1000Date20260409000000 extends SimpleMigrationStep {

    public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
        /** @var ISchemaWrapper $schema */
        $schema = $schemaClosure();

        // Activities table
        if (!$schema->hasTable('fit_tracker_activities')) {
            $table = $schema->createTable('fit_tracker_activities');
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
            $table->addColumn('sport', Types::STRING, [
                'notnull' => true,
                'length' => 32, // running, cycling, gym
            ]);
            $table->addColumn('start_time', Types::DATETIME, [
                'notnull' => true,
            ]);
            $table->addColumn('duration', Types::INTEGER, [
                'notnull' => true,
                'comment' => 'seconds',
            ]);
            $table->addColumn('distance', Types::FLOAT, [
                'notnull' => false,
                'default' => null,
                'comment' => 'meters',
            ]);
            $table->addColumn('elevation_gain', Types::FLOAT, [
                'notnull' => false,
                'default' => null,
                'comment' => 'meters',
            ]);
            $table->addColumn('avg_hr', Types::INTEGER, [
                'notnull' => false,
                'default' => null,
            ]);
            $table->addColumn('max_hr', Types::INTEGER, [
                'notnull' => false,
                'default' => null,
            ]);
            $table->addColumn('calories', Types::INTEGER, [
                'notnull' => false,
                'default' => null,
            ]);
            $table->addColumn('avg_speed', Types::FLOAT, [
                'notnull' => false,
                'default' => null,
                'comment' => 'm/s',
            ]);
            $table->addColumn('max_speed', Types::FLOAT, [
                'notnull' => false,
                'default' => null,
                'comment' => 'm/s',
            ]);
            $table->addColumn('avg_cadence', Types::INTEGER, [
                'notnull' => false,
                'default' => null,
            ]);
            $table->addColumn('avg_power', Types::INTEGER, [
                'notnull' => false,
                'default' => null,
                'comment' => 'watts, cycling only',
            ]);
            $table->addColumn('fit_file_path', Types::STRING, [
                'notnull' => true,
                'length' => 1024,
            ]);
            $table->addColumn('created_at', Types::DATETIME, [
                'notnull' => true,
            ]);
            $table->setPrimaryKey(['id']);
            $table->addIndex(['user_id'], 'fit_act_uid');
            $table->addIndex(['user_id', 'start_time'], 'fit_act_uid_time');
        }

        // Laps table
        if (!$schema->hasTable('fit_tracker_laps')) {
            $table = $schema->createTable('fit_tracker_laps');
            $table->addColumn('id', Types::INTEGER, [
                'autoincrement' => true,
                'notnull' => true,
            ]);
            $table->addColumn('activity_id', Types::INTEGER, [
                'notnull' => true,
            ]);
            $table->addColumn('lap_number', Types::INTEGER, [
                'notnull' => true,
            ]);
            $table->addColumn('start_time', Types::DATETIME, [
                'notnull' => false,
                'default' => null,
            ]);
            $table->addColumn('duration', Types::INTEGER, [
                'notnull' => false,
                'default' => null,
                'comment' => 'seconds',
            ]);
            $table->addColumn('distance', Types::FLOAT, [
                'notnull' => false,
                'default' => null,
            ]);
            $table->addColumn('avg_hr', Types::INTEGER, [
                'notnull' => false,
                'default' => null,
            ]);
            $table->addColumn('max_hr', Types::INTEGER, [
                'notnull' => false,
                'default' => null,
            ]);
            $table->addColumn('avg_speed', Types::FLOAT, [
                'notnull' => false,
                'default' => null,
            ]);
            $table->addColumn('elevation_gain', Types::FLOAT, [
                'notnull' => false,
                'default' => null,
            ]);
            $table->addColumn('calories', Types::INTEGER, [
                'notnull' => false,
                'default' => null,
            ]);
            $table->setPrimaryKey(['id']);
            $table->addIndex(['activity_id'], 'fit_tracker_laps_aid');
        }

        // Trackpoints table
        if (!$schema->hasTable('fit_tracker_tp')) {
            $table = $schema->createTable('fit_tracker_tp');
            $table->addColumn('id', Types::INTEGER, [
                'autoincrement' => true,
                'notnull' => true,
            ]);
            $table->addColumn('activity_id', Types::INTEGER, [
                'notnull' => true,
            ]);
            $table->addColumn('timestamp', Types::DATETIME, [
                'notnull' => true,
            ]);
            $table->addColumn('lat', Types::FLOAT, [
                'notnull' => false,
                'default' => null,
            ]);
            $table->addColumn('lon', Types::FLOAT, [
                'notnull' => false,
                'default' => null,
            ]);
            $table->addColumn('altitude', Types::FLOAT, [
                'notnull' => false,
                'default' => null,
            ]);
            $table->addColumn('distance', Types::FLOAT, [
                'notnull' => false,
                'default' => null,
            ]);
            $table->addColumn('heart_rate', Types::INTEGER, [
                'notnull' => false,
                'default' => null,
            ]);
            $table->addColumn('speed', Types::FLOAT, [
                'notnull' => false,
                'default' => null,
            ]);
            $table->addColumn('cadence', Types::INTEGER, [
                'notnull' => false,
                'default' => null,
            ]);
            $table->addColumn('power', Types::INTEGER, [
                'notnull' => false,
                'default' => null,
            ]);
            $table->setPrimaryKey(['id']);
            $table->addIndex(['activity_id'], 'fit_tracker_tp_aid');
        }

        return $schema;
    }
}
