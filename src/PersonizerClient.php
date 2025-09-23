<?php

namespace wimdevgroup\PersonizerPhp;

use DateMalformedStringException;
use DateTime;
use DateTimeZone;
use Exception;
use InvalidArgumentException;

class PersonizerClient
{
    private const ICS_URL = "https://www.personizer.com/ical/";
    private const TIMEZONE = "Europe/Berlin";

    private string $calId;
    private DateTimeZone $timezone;

    public function __construct(string $calId)
    {
        $this->calId = $calId;
        $this->timezone = new DateTimeZone(self::TIMEZONE);
    }

    /**
     * @throws DateMalformedStringException
     */
    public function getEvents(array $options = []): array
    {
        $defaultOptions = [
            'query' => null,
            'limit' => 10,
            'future_only' => true,
            'days_ahead' => null,
            'today' => false
        ];

        $options = array_merge($defaultOptions, $options);

        $this->validateOptions($options);

        $events = $this->fetchEvents();
        $filteredEvents = $this->filterEvents($events, $options);

        return array_slice($filteredEvents, 0, $options['limit']);
    }

    private function fetchEvents(): array
    {
        $icsData = file_get_contents(self::ICS_URL . $this->calId);

        if ($icsData === false) {
            throw new \RuntimeException("Failed to fetch calendar data");
        }

        return $this->parseIcsData($icsData);
    }

    private function parseIcsData(string $icsData): array
    {
        $events = [];
        $lines = explode("\n", str_replace("\r", "", $icsData));
        $currentEvent = null;

        foreach ($lines as $line) {
            $line = trim($line);

            if ($line === 'BEGIN:VEVENT') {
                $currentEvent = [];
            } elseif ($line === 'END:VEVENT' && $currentEvent !== null) {
                if (isset($currentEvent['DTSTART']) && isset($currentEvent['SUMMARY'])) {
                    $events[] = $this->buildEventArray($currentEvent);
                }
                $currentEvent = null;
            } elseif ($currentEvent !== null && strpos($line, ':') !== false) {
                $parts = explode(':', $line, 2);
                $key = $parts[0];
                $value = $parts[1];

                if (str_contains($key, ';')) {
                    $keyParts = explode(';', $key);
                    $key = $keyParts[0];
                }

                $currentEvent[$key] = $value;
            }
        }

        usort($events, function($a, $b) {
            return $a['start_datetime'] <=> $b['start_datetime'];
        });

        return $events;
    }

    private function buildEventArray(array $eventData): array
    {
        $startDateTime = $this->parseDateTime($eventData['DTSTART']);
        $endDateTime = isset($eventData['DTEND']) ? $this->parseDateTime($eventData['DTEND']) : null;

        return [
            'title' => $eventData['SUMMARY'] ?? '',
            'start' => $startDateTime->format('c'),
            'end' => $endDateTime?->format('c'),
            'description' => $eventData['DESCRIPTION'] ?? '',
            'location' => $eventData['LOCATION'] ?? '',
            'start_datetime' => $startDateTime,
            'end_datetime' => $endDateTime
        ];
    }

    private function parseDateTime(string $dateTimeString): DateTime
    {
        if (str_contains($dateTimeString, 'TZID=')) {
            $dateTimeString = preg_replace('/TZID=[^:]*:/', '', $dateTimeString);
        }

        if (strlen($dateTimeString) === 8) {
            $dateTime = DateTime::createFromFormat('Ymd', $dateTimeString, $this->timezone);
        } else {
            $format = str_ends_with($dateTimeString, 'Z') ? 'Ymd\THis\Z' : 'Ymd\THis';
            $timezone = str_ends_with($dateTimeString, 'Z') ? new DateTimeZone('UTC') : $this->timezone;
            $dateTime = DateTime::createFromFormat($format, $dateTimeString, $timezone);

            if (str_ends_with($dateTimeString, 'Z')) {
                $dateTime->setTimezone($this->timezone);
            }
        }

        if (!$dateTime) {
            throw new \RuntimeException("Failed to parse datetime: $dateTimeString");
        }

        return $dateTime;
    }

    /**
     * @throws DateMalformedStringException
     * @throws Exception
     */
    private function filterEvents(array $events, array $options): array
    {
        $now = new DateTime('now', $this->timezone);
        $filteredEvents = [];

        foreach ($events as $event) {
            $startDateTime = $event['start_datetime'];
            $endDateTime = $event['end_datetime'];

            if ($options['today']) {
                $todayStart = clone $now;
                $todayStart->setTime(0, 0, 0);
                $todayEnd = clone $todayStart;
                $todayEnd->modify('+1 day');

                $eventEndTime = $endDateTime ?? $startDateTime;
                if ($startDateTime >= $todayEnd || $eventEndTime < $todayStart) {
                    continue;
                }
            }
            elseif ($options['future_only']) {
                $eventEndTime = $endDateTime ?? $startDateTime;
                if ($eventEndTime < $now) {
                    continue;
                }
            }

            if ($options['days_ahead'] !== null) {
                $endDate = clone $now;
                $endDate->modify('+' . $options['days_ahead'] . ' days');
                if ($startDateTime > $endDate) {
                    continue;
                }
            }

            if ($options['query'] !== null &&
                stripos($event['title'], $options['query']) === false) {
                continue;
            }

            unset($event['start_datetime'], $event['end_datetime']);
            $filteredEvents[] = $event;
        }

        return $filteredEvents;
    }

    private function validateOptions(array $options): void
    {
        if ($options['limit'] < 1 || $options['limit'] > 100) {
            throw new InvalidArgumentException("Limit must be between 1 and 100");
        }

        if ($options['days_ahead'] !== null && ($options['days_ahead'] < 1 || $options['days_ahead'] > 365)) {
            throw new InvalidArgumentException("Days ahead must be between 1 and 365");
        }
    }
}
