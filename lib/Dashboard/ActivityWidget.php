<?php

declare(strict_types=1);

namespace OCA\FitTracker\Dashboard;

use OCA\FitTracker\AppInfo\Application;
use OCA\FitTracker\Db\Activity;
use OCA\FitTracker\Db\ActivityMapper;
use OCA\FitTracker\Db\MetaMapper;
use OCA\FitTracker\Db\SleepMapper;
use OCA\FitTracker\Service\SettingsService;
use OCP\Dashboard\IAPIWidgetV2;
use OCP\Dashboard\IButtonWidget;
use OCP\Dashboard\IIconWidget;
use OCP\Dashboard\Model\WidgetButton;
use OCP\Dashboard\Model\WidgetItem;
use OCP\Dashboard\Model\WidgetItems;
use OCP\IDateTimeFormatter;
use OCP\IL10N;
use OCP\IURLGenerator;

/**
 * Nextcloud Dashboard widget: this week's totals, the last sleep and the
 * latest activities. Rendered by the dashboard itself (API widget), so it
 * needs no script of its own.
 */
class ActivityWidget implements IAPIWidgetV2, IIconWidget, IButtonWidget {
    public function __construct(
        private IL10N              $l10n,
        private IURLGenerator      $urlGenerator,
        private IDateTimeFormatter $dateFormatter,
        private ActivityMapper     $activityMapper,
        private SleepMapper        $sleepMapper,
        private MetaMapper         $metaMapper,
        private SettingsService    $settings,
    ) {}

    public function getId(): string {
        return 'fit_tracker-activities';
    }

    public function getTitle(): string {
        return $this->l10n->t('Fitness');
    }

    public function getOrder(): int {
        return 60;
    }

    public function getIconClass(): string {
        return 'icon-fit-tracker';
    }

    public function getIconUrl(): string {
        return $this->urlGenerator->getAbsoluteURL($this->urlGenerator->imagePath(Application::APP_ID, 'app-dark.svg'));
    }

    public function getUrl(): ?string {
        return $this->appUrl('');
    }

    public function load(): void {
    }

    public function getWidgetButtons(string $userId): array {
        return [new WidgetButton(WidgetButton::TYPE_MORE, $this->appUrl('/stats'), $this->l10n->t('Trends'))];
    }

    public function getItemsV2(string $userId, ?string $since = null, int $limit = 7): WidgetItems {
        $activities = $this->activityMapper->findAllForUser($userId);
        $imperial = $this->settings->isImperial($userId);
        $icon = $this->getIconUrl();
        $items = [];

        // Monday 00:00 of this week, in UTC like the stored start times
        $weekStart = (new \DateTimeImmutable('monday this week', new \DateTimeZone('UTC')))->format('Y-m-d H:i:s');
        $week = array_filter($activities, fn(Activity $a) => $a->getStartTime() >= $weekStart);
        $distance = array_sum(array_map(fn(Activity $a) => (float) $a->getDistance(), $week));
        $duration = array_sum(array_map(fn(Activity $a) => $a->getDuration(), $week));
        $items[] = new WidgetItem(
            $this->l10n->n('%n activity this week', '%n activities this week', count($week)),
            $this->formatDistance($distance, $imperial) . ' · ' . $this->formatDuration($duration),
            $this->appUrl('/stats'),
            $icon,
            'week',
        );

        $sleep = $this->sleepMapper->findAllForUser($userId)[0] ?? null;
        if ($sleep !== null) {
            $subtitle = $this->formatDuration($sleep->getDuration());
            if ($sleep->getScore()) {
                $subtitle .= ' · ' . $this->l10n->t('Score %s/100', [$sleep->getScore()]);
            }
            $items[] = new WidgetItem(
                $this->l10n->t('Last sleep, %s', [$this->formatDate($sleep->getStartTime())]),
                $subtitle,
                $this->appUrl('/sleep/' . $sleep->getId()),
                $icon,
                'sleep',
            );
        }

        $metaByFile = $this->metaMapper->findAllForUser($userId);
        foreach (array_slice($activities, 0, max(0, $limit - count($items))) as $a) {
            $name = ($metaByFile[(int) $a->getFileId()] ?? null)?->getCustomName() ?: $a->getName();
            $parts = [$this->formatDate($a->getStartTime())];
            if ($a->getDistance()) {
                $parts[] = $this->formatDistance((float) $a->getDistance(), $imperial);
            }
            $parts[] = $this->formatDuration($a->getDuration());
            $items[] = new WidgetItem($name, implode(' · ', $parts), $this->appUrl('/activity/' . $a->getId()), $icon, (string) $a->getId());
        }

        return new WidgetItems(
            $items,
            $activities ? '' : $this->l10n->t('No activities imported yet'),
        );
    }

    private function appUrl(string $route): string {
        return $this->urlGenerator->linkToRouteAbsolute('fit_tracker.page.index') . ($route !== '' ? '#' . $route : '');
    }

    private function formatDistance(float $km, bool $imperial): string {
        return $imperial
            ? $this->l10n->t('%s mi', [number_format($km / 1.609344, 1)])
            : $this->l10n->t('%s km', [number_format($km, 1)]);
    }

    private function formatDuration(int $seconds): string {
        $h = intdiv($seconds, 3600);
        $m = intdiv($seconds % 3600, 60);
        return $h > 0 ? $this->l10n->t('%1$sh %2$sm', [$h, $m]) : $this->l10n->t('%sm', [$m]);
    }

    private function formatDate(string $utc): string {
        return $this->dateFormatter->formatDate(new \DateTime($utc, new \DateTimeZone('UTC')), 'medium');
    }
}
