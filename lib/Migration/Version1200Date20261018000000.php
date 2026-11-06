<?php

declare(strict_types=1);

namespace OCA\FitTracker\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\DB\Types;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

/**
 * Analysis results on activities, plus tables for what the user adds by hand
 * (names, notes, gear, goals, share links).
 *
 * User-entered data is keyed by the Nextcloud file id of the .fit file rather
 * than by activity id: activity rows are dropped and recreated on every
 * re-import, the file id survives that (and renames/moves of the file).
 */
class Version1200Date20261018000000 extends SimpleMigrationStep {

    public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
        /** @var ISchemaWrapper $schema */
        $schema = $schemaClosure();

        $activities = $schema->getTable('fit_tracker_activities');
        if (!$activities->hasColumn('file_id')) {
            $activities->addColumn('file_id', Types::BIGINT, [
                'notnull' => false,
                'default' => null,
                'comment' => 'Nextcloud file id of the .fit file',
            ]);
        }
        if (!$activities->hasColumn('analysis')) {
            $activities->addColumn('analysis', Types::TEXT, [
                'notnull' => false,
                'default' => null,
                'comment' => 'JSON: HR histogram, best efforts, power curve',
            ]);
        }
        if (!$activities->hasColumn('analysis_version')) {
            $activities->addColumn('analysis_version', Types::INTEGER, [
                'notnull' => true,
                'default' => 0,
            ]);
        }
        if (!$activities->hasIndex('fit_act_uid_time')) {
            $activities->addIndex(['user_id', 'start_time'], 'fit_act_uid_time');
        }

        if (!$schema->hasTable('fit_tracker_meta')) {
            $table = $schema->createTable('fit_tracker_meta');
            $table->addColumn('id', Types::INTEGER, ['autoincrement' => true, 'notnull' => true]);
            $table->addColumn('user_id', Types::STRING, ['notnull' => true, 'length' => 64]);
            $table->addColumn('file_id', Types::BIGINT, ['notnull' => true]);
            $table->addColumn('custom_name', Types::STRING, ['notnull' => false, 'length' => 255, 'default' => null]);
            $table->addColumn('notes', Types::TEXT, ['notnull' => false, 'default' => null]);
            $table->addColumn('rpe', Types::SMALLINT, ['notnull' => false, 'default' => null, 'comment' => 'perceived effort 1-10']);
            $table->addColumn('gear_id', Types::INTEGER, ['notnull' => false, 'default' => null, 'comment' => 'null = default gear for the sport, 0 = no gear']);
            $table->addColumn('weather', Types::TEXT, ['notnull' => false, 'default' => null, 'comment' => 'cached JSON from Open-Meteo']);
            $table->setPrimaryKey(['id']);
            $table->addUniqueIndex(['user_id', 'file_id'], 'fit_meta_uid_fid');
        }

        if (!$schema->hasTable('fit_tracker_goals')) {
            $table = $schema->createTable('fit_tracker_goals');
            $table->addColumn('id', Types::INTEGER, ['autoincrement' => true, 'notnull' => true]);
            $table->addColumn('user_id', Types::STRING, ['notnull' => true, 'length' => 64]);
            $table->addColumn('sport', Types::STRING, ['notnull' => false, 'length' => 64, 'default' => null, 'comment' => 'null = all sports']);
            $table->addColumn('metric', Types::STRING, ['notnull' => true, 'length' => 16, 'comment' => 'distance, duration, count, elevation']);
            $table->addColumn('period', Types::STRING, ['notnull' => true, 'length' => 16, 'comment' => 'week, month, year']);
            $table->addColumn('target', Types::FLOAT, ['notnull' => true, 'comment' => 'km, hours, activities or meters']);
            $table->addColumn('created_at', Types::DATETIME, ['notnull' => true]);
            $table->setPrimaryKey(['id']);
            $table->addIndex(['user_id'], 'fit_goal_uid');
        }

        if (!$schema->hasTable('fit_tracker_gear')) {
            $table = $schema->createTable('fit_tracker_gear');
            $table->addColumn('id', Types::INTEGER, ['autoincrement' => true, 'notnull' => true]);
            $table->addColumn('user_id', Types::STRING, ['notnull' => true, 'length' => 64]);
            $table->addColumn('name', Types::STRING, ['notnull' => true, 'length' => 255]);
            $table->addColumn('type', Types::STRING, ['notnull' => true, 'length' => 16, 'comment' => 'shoes, bike, other']);
            $table->addColumn('default_sport', Types::STRING, ['notnull' => false, 'length' => 64, 'default' => null]);
            $table->addColumn('start_date', Types::STRING, ['notnull' => false, 'length' => 10, 'default' => null, 'comment' => 'Y-m-d; default gear applies from this day']);
            $table->addColumn('initial_distance', Types::FLOAT, ['notnull' => true, 'default' => 0, 'comment' => 'km already used before tracking']);
            $table->addColumn('retire_distance', Types::FLOAT, ['notnull' => false, 'default' => null, 'comment' => 'km after which to replace it']);
            $table->addColumn('retired_date', Types::STRING, ['notnull' => false, 'length' => 10, 'default' => null, 'comment' => 'Y-m-d; default gear stops applying after this day']);
            $table->addColumn('created_at', Types::DATETIME, ['notnull' => true]);
            $table->setPrimaryKey(['id']);
            $table->addIndex(['user_id'], 'fit_gear_uid');
        }

        if (!$schema->hasTable('fit_tracker_shares')) {
            $table = $schema->createTable('fit_tracker_shares');
            $table->addColumn('id', Types::INTEGER, ['autoincrement' => true, 'notnull' => true]);
            $table->addColumn('user_id', Types::STRING, ['notnull' => true, 'length' => 64]);
            $table->addColumn('file_id', Types::BIGINT, ['notnull' => true]);
            $table->addColumn('token', Types::STRING, ['notnull' => true, 'length' => 32]);
            $table->addColumn('hide_ends', Types::BOOLEAN, ['notnull' => false, 'default' => true, 'comment' => 'hide first and last 200 m of the route']);
            $table->addColumn('created_at', Types::DATETIME, ['notnull' => true]);
            $table->setPrimaryKey(['id']);
            $table->addUniqueIndex(['token'], 'fit_share_token');
            $table->addIndex(['user_id', 'file_id'], 'fit_share_uid_fid');
        }

        return $schema;
    }
}
