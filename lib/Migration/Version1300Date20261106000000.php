<?php

declare(strict_types=1);

namespace OCA\FitTracker\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\DB\Types;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

/** Threshold for goals that count nights, e.g. "5 nights with at least 7 h of sleep". */
class Version1300Date20261106000000 extends SimpleMigrationStep {

    public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
        /** @var ISchemaWrapper $schema */
        $schema = $schemaClosure();
        $goals = $schema->getTable('fit_tracker_goals');
        if ($goals->hasColumn('threshold')) {
            return null;
        }
        $goals->addColumn('threshold', Types::FLOAT, [
            'notnull' => false,
            'default' => null,
            'comment' => 'hours of sleep a night must reach to count (sleep_nights)',
        ]);
        return $schema;
    }
}
