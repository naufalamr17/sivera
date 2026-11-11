{{ $value ? \Carbon\CarbonImmutable::parse($value, 'UTC')->setTimezone(config('sivera.timezone'))->format('d M Y H:i:s T') : '—' }}
