<?php

declare(strict_types=1);

namespace OCA\FitTracker\Service;

use OCA\FitTracker\Db\Activity;
use OCA\FitTracker\Db\Gear;
use OCA\FitTracker\Db\GearMapper;
use OCA\FitTracker\Db\Meta;
use OCA\FitTracker\Db\MetaMapper;

/**
 * Shoes, bikes and other gear, with the distance put on each.
 *
 * An activity uses the gear picked for it by hand (Meta.gearId; 0 = none),
 * otherwise the gear that is the default for its sport on that day: the one
 * with the latest start date on or before the activity, not yet retired. So
 * adding new shoes with a start date takes over from the old pair without
 * touching past activities.
 */
class GearService {
    public function __construct(
        private GearMapper $gearMapper,
        private MetaMapper $metaMapper,
    ) {}

    /** @return Gear[] */
    public function findAll(string $userId): array {
        return $this->gearMapper->findAllForUser($userId);
    }

    /** @throws \OCP\AppFramework\Db\DoesNotExistException */
    public function find(int $id, string $userId): Gear {
        return $this->gearMapper->findForUser($id, $userId);
    }

    /**
     * @param Gear[] $gears
     */
    public static function effectiveGearId(Activity $activity, ?Meta $meta, array $gears): ?int {
        $explicit = $meta?->getGearId();
        if ($explicit !== null) {
            return $explicit > 0 ? $explicit : null;
        }
        $day = substr($activity->getStartTime(), 0, 10);
        $best = null;
        foreach ($gears as $gear) {
            if ($gear->getDefaultSport() !== $activity->getSport()) {
                continue;
            }
            $start = $gear->getStartDate();
            $retired = $gear->getRetiredDate();
            if (($start !== null && $start > $day) || ($retired !== null && $retired < $day)) {
                continue;
            }
            if ($best === null || (string) $start > (string) $best->getStartDate()
                || ((string) $start === (string) $best->getStartDate() && $gear->getId() > $best->getId())) {
                $best = $gear;
            }
        }
        return $best?->getId();
    }

    /**
     * Gear with usage totals over the given activities.
     *
     * @param Activity[] $activities
     * @param array<int, Meta> $metaByFile
     */
    public function listWithUsage(string $userId, array $activities, array $metaByFile): array {
        $gears = $this->findAll($userId);
        $usage = [];
        foreach ($activities as $a) {
            $meta = $a->getFileId() !== null ? ($metaByFile[$a->getFileId()] ?? null) : null;
            $gearId = self::effectiveGearId($a, $meta, $gears);
            if ($gearId === null) {
                continue;
            }
            $u = $usage[$gearId] ?? ['distance' => 0.0, 'duration' => 0, 'count' => 0, 'lastUsed' => null];
            $u['distance'] += (float) $a->getDistance();
            $u['duration'] += $a->getDuration();
            $u['count']++;
            if ($u['lastUsed'] === null || $a->getStartTime() > $u['lastUsed']) {
                $u['lastUsed'] = $a->getStartTime();
            }
            $usage[$gearId] = $u;
        }

        return array_map(function (Gear $g) use ($usage) {
            $u = $usage[$g->getId()] ?? ['distance' => 0.0, 'duration' => 0, 'count' => 0, 'lastUsed' => null];
            return $g->toArray() + [
                'totalDistance' => round($g->getInitialDistance() + $u['distance'], 2),
                'activityCount' => $u['count'],
                'totalDuration' => $u['duration'],
                'lastUsed'      => $u['lastUsed'],
            ];
        }, $gears);
    }

    /** @throws \InvalidArgumentException */
    public function create(string $userId, array $fields): Gear {
        $gear = new Gear();
        $gear->setUserId($userId);
        $gear->setCreatedAt((new \DateTime())->format('Y-m-d H:i:s'));
        $this->apply($gear, $fields + ['name' => '', 'type' => 'other']);
        return $this->gearMapper->insert($gear);
    }

    /** @throws \InvalidArgumentException|\OCP\AppFramework\Db\DoesNotExistException */
    public function update(int $id, string $userId, array $fields): Gear {
        $gear = $this->gearMapper->findForUser($id, $userId);
        $this->apply($gear, $fields);
        return $this->gearMapper->update($gear);
    }

    /** @throws \OCP\AppFramework\Db\DoesNotExistException */
    public function delete(int $id, string $userId): void {
        $gear = $this->gearMapper->findForUser($id, $userId);
        $this->metaMapper->clearGear($userId, $id);
        $this->gearMapper->delete($gear);
    }

    private function apply(Gear $gear, array $f): void {
        if (array_key_exists('name', $f)) {
            $name = trim((string) $f['name']);
            if ($name === '' || mb_strlen($name) > 255) {
                throw new \InvalidArgumentException('Name is required');
            }
            $gear->setName($name);
        }
        if (array_key_exists('type', $f)) {
            if (!in_array($f['type'], Gear::TYPES, true)) {
                throw new \InvalidArgumentException('Invalid gear type');
            }
            $gear->setType($f['type']);
        }
        if (array_key_exists('defaultSport', $f)) {
            $sport = trim((string) $f['defaultSport']);
            $gear->setDefaultSport($sport === '' ? null : mb_substr($sport, 0, 64));
        }
        foreach (['startDate' => 'setStartDate', 'retiredDate' => 'setRetiredDate'] as $key => $setter) {
            if (array_key_exists($key, $f)) {
                $gear->$setter(self::date($f[$key]));
            }
        }
        if (array_key_exists('initialDistance', $f)) {
            $gear->setInitialDistance(max(0.0, (float) $f['initialDistance']));
        }
        if (array_key_exists('retireDistance', $f)) {
            $v = $f['retireDistance'];
            $gear->setRetireDistance($v === null || $v === '' || (float) $v <= 0 ? null : (float) $v);
        }
    }

    private static function date(mixed $value): ?string {
        if ($value === null || $value === '') {
            return null;
        }
        $d = \DateTime::createFromFormat('!Y-m-d', (string) $value);
        if ($d === false || $d->format('Y-m-d') !== $value) {
            throw new \InvalidArgumentException('Dates must be YYYY-MM-DD');
        }
        return $value;
    }
}
