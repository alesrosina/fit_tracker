<?php

declare(strict_types=1);

namespace OCA\FitTracker\Service;

use OCA\FitTracker\Db\Goal;
use OCA\FitTracker\Db\GoalMapper;

/**
 * Weekly, monthly or yearly targets. Progress is worked out in the browser:
 * on the dashboard scaled to the selected timeframe, on the goals page per
 * calendar period.
 */
class GoalService {
    public function __construct(private GoalMapper $goalMapper) {}

    /** @return array[] */
    public function list(string $userId): array {
        return array_map(fn(Goal $g) => $g->toArray(), $this->goalMapper->findAllForUser($userId));
    }

    /** @throws \InvalidArgumentException */
    public function create(string $userId, array $fields): array {
        $goal = new Goal();
        $goal->setUserId($userId);
        $goal->setCreatedAt((new \DateTime())->format('Y-m-d H:i:s'));
        $this->apply($goal, $fields, true);
        return $this->goalMapper->insert($goal)->toArray();
    }

    /** @throws \InvalidArgumentException|\OCP\AppFramework\Db\DoesNotExistException */
    public function update(int $id, string $userId, array $fields): array {
        $goal = $this->goalMapper->findForUser($id, $userId);
        $this->apply($goal, $fields, false);
        return $this->goalMapper->update($goal)->toArray();
    }

    /** @throws \OCP\AppFramework\Db\DoesNotExistException */
    public function delete(int $id, string $userId): void {
        $this->goalMapper->delete($this->goalMapper->findForUser($id, $userId));
    }

    private function apply(Goal $goal, array $f, bool $isNew): void {
        foreach (['metric' => Goal::METRICS, 'period' => Goal::PERIODS] as $key => $allowed) {
            if ($isNew || array_key_exists($key, $f)) {
                if (!in_array($f[$key] ?? null, $allowed, true)) {
                    throw new \InvalidArgumentException("Invalid $key");
                }
                $goal->{'set' . ucfirst($key)}($f[$key]);
            }
        }
        if ($isNew || array_key_exists('target', $f)) {
            $target = $f['target'] ?? null;
            if (!is_numeric($target) || (float) $target <= 0 || (float) $target > 1e7) {
                throw new \InvalidArgumentException('Target must be a positive number');
            }
            $goal->setTarget((float) $target);
        }
        if (array_key_exists('sport', $f)) {
            $sport = trim((string) $f['sport']);
            $goal->setSport($sport === '' ? null : mb_substr($sport, 0, 64));
        }
        $isSleep = in_array($goal->getMetric(), Goal::SLEEP_METRICS, true);
        if ($isSleep) {
            // Sleep isn't tied to a sport
            $goal->setSport(null);
        }
        if ($goal->getMetric() === 'sleep_score' && $goal->getTarget() > 100) {
            throw new \InvalidArgumentException('Sleep score goes up to 100');
        }
        if ($goal->getMetric() === 'sleep_hours' && $goal->getTarget() > 24) {
            throw new \InvalidArgumentException('A night has at most 24 hours');
        }
        if ($goal->getMetric() === 'sleep_nights') {
            $threshold = array_key_exists('threshold', $f) ? $f['threshold'] : $goal->getThreshold();
            if (!is_numeric($threshold) || (float) $threshold <= 0 || (float) $threshold > 24) {
                throw new \InvalidArgumentException('Hours per night must be between 0 and 24');
            }
            $goal->setThreshold((float) $threshold);
        } elseif ($goal->getThreshold() !== null) {
            $goal->setThreshold(null);
        }
    }
}
