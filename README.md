# Personizer PHP Client

PHP client library für die Personizer Kalender API. Ermöglicht das Abrufen und Filtern von Kalenderevents aus ICS-Feeds.

## Installation

```bash
composer require wimdevgroup/personizer-php
```

## Verwendung

### Grundlegende Verwendung

```php
<?php
require_once 'vendor/autoload.php';

use Wimdevgroup\PersonizerPhp\PersonizerClient;

// Client mit Kalender-ID oder Token initialisieren
$client = new PersonizerClient('your-calendar-id-or-token');

// Alle Events abrufen (Standardeinstellungen)
$events = $client->getEvents();
```

### Erweiterte Filteroptionen

```php
// Events mit verschiedenen Filteroptionen abrufen
$events = $client->getEvents([
    'query' => 'Meeting',           // Filter nach Titelinhalt
    'limit' => 20,                  // Maximale Anzahl Events (Standard: 10, Max: 100)
    'future_only' => true,          // Nur zukünftige Events (Standard: true)
    'days_ahead' => 30,             // Events der nächsten X Tage
    'today' => false                // Nur heutige Events (Standard: false)
]);
```

### Filteroptionen im Detail

| Option | Typ | Standard | Beschreibung |
|--------|-----|----------|--------------|
| `query` | string\|null | null | Filtert Events nach Titelinhalt (case-insensitive) |
| `limit` | int | 10 | Maximale Anzahl zurückgegebener Events (1-100) |
| `future_only` | bool | true | Zeigt nur Events in der Zukunft |
| `days_ahead` | int\|null | null | Begrenzt Events auf die nächsten X Tage (1-365) |
| `today` | bool | false | Zeigt nur Events vom heutigen Tag |

### Beispiele

```php
// Nur Events von heute
$todayEvents = $client->getEvents(['today' => true]);

// Events der nächsten 7 Tage mit "Termin" im Titel
$weekEvents = $client->getEvents([
    'query' => 'Termin',
    'days_ahead' => 7,
    'limit' => 50
]);

// Alle vergangenen und zukünftigen Events
$allEvents = $client->getEvents([
    'future_only' => false,
    'limit' => 100
]);
```

### Event-Datenstruktur

Jedes Event wird als Array mit folgenden Feldern zurückgegeben:

```php
[
    'title' => 'Event Titel',
    'start' => '2024-01-15T10:00:00+01:00',    // ISO 8601 Format
    'end' => '2024-01-15T11:00:00+01:00',      // ISO 8601 Format oder null
    'description' => 'Event Beschreibung',
    'location' => 'Event Ort'
]
```

## Zeitzone

Alle Zeiten werden automatisch in die Zeitzone `Europe/Berlin` konvertiert.

## Fehlerbehandlung

```php
try {
    $events = $client->getEvents();
} catch (\RuntimeException $e) {
    echo "Fehler beim Abrufen der Kalenderdaten: " . $e->getMessage();
} catch (\InvalidArgumentException $e) {
    echo "Ungültige Parameter: " . $e->getMessage();
}
```

## Systemanforderungen

- PHP >= 8.0
- ext-json

## Lizenz

MIT