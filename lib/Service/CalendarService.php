<?php

declare(strict_types=1);

namespace OCA\FitTracker\Service;

use OCA\FitTracker\Db\Activity;
use OCP\Calendar\ICalendar;
use OCP\Calendar\ICalendarIsWritable;
use OCP\Calendar\ICreateFromString;
use OCP\Calendar\IManager;
use OCP\IURLGenerator;
use Psr\Log\LoggerInterface;

/**
 * Optionally puts every imported activity into one of the user's Nextcloud
 * calendars as an event. Off until the user picks a calendar in settings.
 *
 * Events get a stable name from the .fit file id, so re-imports and the
 * "add all" button don't create duplicates.
 */
class CalendarService {
    public function __construct(
        private IManager        $calendarManager,
        private SettingsService $settings,
        private IURLGenerator   $urlGenerator,
        private LoggerInterface $logger,
    ) {}

    /** @return array<array{uri: string, name: string, color: ?string}> */
    public function writableCalendars(string $userId): array {
        $out = [];
        foreach ($this->calendarManager->getCalendarsForPrincipal('principals/users/' . $userId) as $calendar) {
            if ($this->isWritable($calendar)) {
                $out[] = ['uri' => $calendar->getUri(), 'name' => $calendar->getDisplayName() ?? $calendar->getUri(), 'color' => $calendar->getDisplayColor()];
            }
        }
        return $out;
    }

    public function isEnabled(string $userId): bool {
        return $this->settings->get($userId, 'calendar_uri') !== '';
    }

    /**
     * Add the activity to the chosen calendar. Returns false when it was
     * already there or no calendar is set; never throws, so an unreachable
     * calendar can't break an import.
     */
    public function addActivity(string $userId, Activity $activity): bool {
        $calendar = $this->targetCalendar($userId);
        if ($calendar === null || $activity->getFileId() === null) {
            return false;
        }
        try {
            $calendar->createFromString($this->objectName($activity), $this->toIcs($userId, $activity));
            return true;
        } catch (\Throwable $e) {
            // Most often: the event already exists (same UID)
            $this->logger->debug('FIT Tracker: calendar event not added: ' . $e->getMessage());
            return false;
        }
    }

    /** @param Activity[] $activities @return int number of events added */
    public function addAll(string $userId, array $activities): int {
        $added = 0;
        foreach ($activities as $activity) {
            if ($this->addActivity($userId, $activity)) {
                $added++;
            }
        }
        return $added;
    }

    /**
     * Remove the activity's event. The public calendar API can only create,
     * so this uses the DAV app's backend when it is there; best effort.
     */
    public function removeActivity(string $userId, Activity $activity): void {
        $calendar = $this->targetCalendar($userId);
        if ($calendar === null || $activity->getFileId() === null || !class_exists(\OCA\DAV\CalDAV\CalDavBackend::class)) {
            return;
        }
        try {
            $backend = \OCP\Server::get(\OCA\DAV\CalDAV\CalDavBackend::class);
            $backend->deleteCalendarObject((int) $calendar->getKey(), $this->objectName($activity));
        } catch (\Throwable $e) {
            $this->logger->debug('FIT Tracker: calendar event not removed: ' . $e->getMessage());
        }
    }

    private function targetCalendar(string $userId): ?ICreateFromString {
        $uri = $this->settings->get($userId, 'calendar_uri');
        if ($uri === '') {
            return null;
        }
        foreach ($this->calendarManager->getCalendarsForPrincipal('principals/users/' . $userId, [$uri]) as $calendar) {
            if ($calendar->getUri() === $uri && $this->isWritable($calendar) && $calendar instanceof ICreateFromString) {
                return $calendar;
            }
        }
        return null;
    }

    private function isWritable(ICalendar $calendar): bool {
        if (!($calendar instanceof ICreateFromString) || $calendar->isDeleted()) {
            return false;
        }
        if ($calendar instanceof ICalendarIsWritable) {
            return $calendar->isWritable();
        }
        return ($calendar->getPermissions() & \OCP\Constants::PERMISSION_CREATE) !== 0;
    }

    private function objectName(Activity $activity): string {
        return 'fit-tracker-' . $activity->getFileId() . '.ics';
    }

    private function toIcs(string $userId, Activity $activity): string {
        $start = new \DateTimeImmutable($activity->getStartTime(), new \DateTimeZone('UTC'));
        $end   = $start->modify('+' . max(60, $activity->getDuration()) . ' seconds');
        $fmt   = fn(\DateTimeImmutable $d) => $d->format('Ymd\THis\Z');

        $imperial = $this->settings->isImperial($userId);
        $parts = [];
        if ($activity->getDistance()) {
            $parts[] = $imperial
                ? sprintf('%.2f mi', $activity->getDistance() / 1.609344)
                : sprintf('%.2f km', $activity->getDistance());
        }
        $d = $activity->getDuration();
        $parts[] = sprintf('%d:%02d:%02d', intdiv($d, 3600), intdiv($d % 3600, 60), $d % 60);
        if ($activity->getAvgHr()) {
            $parts[] = $activity->getAvgHr() . ' bpm';
        }
        if ($activity->getCalories()) {
            $parts[] = $activity->getCalories() . ' kcal';
        }
        $url = $this->urlGenerator->linkToRouteAbsolute('fit_tracker.page.index') . '#/activity/' . $activity->getId();

        $lines = [
            'BEGIN:VCALENDAR',
            'VERSION:2.0',
            'PRODID:-//Nextcloud//FIT Tracker//EN',
            'BEGIN:VEVENT',
            'UID:fit-tracker-' . $activity->getFileId() . '@' . $userId,
            'DTSTAMP:' . $fmt(new \DateTimeImmutable('now', new \DateTimeZone('UTC'))),
            'DTSTART:' . $fmt($start),
            'DTEND:' . $fmt($end),
            'SUMMARY:' . self::escape($activity->getName()),
            'DESCRIPTION:' . self::escape(implode(' · ', $parts)),
            'URL:' . $url,
            'CATEGORIES:FIT Tracker',
            'TRANSP:TRANSPARENT',
            'END:VEVENT',
            'END:VCALENDAR',
        ];
        return implode("\r\n", array_map([self::class, 'fold'], $lines)) . "\r\n";
    }

    private static function escape(string $text): string {
        return str_replace(['\\', ';', ',', "\n"], ['\\\\', '\\;', '\\,', '\\n'], $text);
    }

    // RFC 5545: lines longer than 75 octets continue on the next line after a space
    private static function fold(string $line): string {
        $out = '';
        while (strlen($line) > 75) {
            $cut = 75;
            // Don't split a multi-byte UTF-8 character
            while ($cut > 0 && (ord($line[$cut]) & 0xC0) === 0x80) {
                $cut--;
            }
            $out .= substr($line, 0, $cut) . "\r\n ";
            $line = substr($line, $cut);
        }
        return $out . $line;
    }
}
