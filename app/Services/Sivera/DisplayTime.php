<?php

namespace App\Services\Sivera;

use Carbon\CarbonImmutable;
use DateTimeInterface;
use Illuminate\Support\Facades\DB;
use Symfony\Component\Uid\Ulid;

class DisplayTime
{
    private ?int $databaseOffset = null;

    public function databaseOffsetSeconds(): int
    {
        return $this->databaseOffset ??= in_array(DB::getDriverName(), ['mysql', 'mariadb'], true)
            ? (int) DB::selectOne('SELECT TIMESTAMPDIFF(SECOND, UTC_TIMESTAMP(), NOW()) AS offset_seconds')->offset_seconds
            : 0;
    }

    public function fromDatabase(DateTimeInterface|string $value): CarbonImmutable
    {
        return CarbonImmutable::parse($value, 'UTC')->subSeconds($this->databaseOffsetSeconds())->utc();
    }

    public function databaseValue(DateTimeInterface $value): CarbonImmutable
    {
        return CarbonImmutable::instance($value)->utc()->addSeconds($this->databaseOffsetSeconds());
    }

    public function format(DateTimeInterface|string|null $value, bool $databaseGenerated = false, ?string $eventId = null): string
    {
        if ($value === null) {
            return '—';
        }

        $time = $databaseGenerated ? $this->fromDatabase($value) : CarbonImmutable::parse($value, 'UTC');
        if ($eventId !== null && Ulid::isValid($eventId)) {
            $eventTime = Ulid::fromString($eventId)->getDateTime();
            $difference = $time->getTimestamp() - $eventTime->getTimestamp();

            // shortcut: legacy offsets require a reporter ULID created with the event; retain source offsets if that contract changes.
            if (in_array($difference, [3600, 25200, 28800], true)) {
                $time = $time->subSeconds($difference);
            }
        }

        return $time->setTimezone(config('sivera.timezone'))->format('d M Y H:i:s T');
    }
}
