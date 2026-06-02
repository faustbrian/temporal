[![GitHub Workflow Status][ico-tests]][link-tests]
[![Latest Version on Packagist][ico-version]][link-packagist]
[![Software License][ico-license]](LICENSE.md)
[![Total Downloads][ico-downloads]][link-downloads]

# temporal

Temporal primitives combining immutable date periods and local time
modelling.

## Requirements

> **Requires [PHP 8.5+](https://php.net/releases/)**

## Installation

```bash
composer require cline/temporal
```

## Usage

```php
use Cline\Temporal\Period\Bounds;
use Cline\Temporal\Period\Period;
use Cline\Temporal\Time\Duration;
use Cline\Temporal\Time\DurationFormat;
use Cline\Temporal\Time\Time;
use Cline\Temporal\Time\TimeFormat;

$period = Period::fromRange('2026-01-01', '2026-01-31', Bounds::IncludeAll);

$time = Time::fromFormat('09:30:00', TimeFormat::Iso8601)
    ->shift(Duration::fromFormat('PT45M', DurationFormat::Iso8601));

echo $period->timeDuration()->totalSeconds();
echo $time->format(TimeFormat::Compact);
```

`Cline\Temporal\Period\*` mirrors the imported Period package surface for
date and datetime ranges.

`Cline\Temporal\Time\*` mirrors the imported Tokei package surface for
local times, durations, circular intervals, and interval sets.

## Change log

Please see [CHANGELOG](CHANGELOG.md) for more information on what has changed recently.

## Contributing

Please see [CONTRIBUTING](CONTRIBUTING.md) and [CODE_OF_CONDUCT](CODE_OF_CONDUCT.md) for details.

## Security

If you discover any security related issues, please use the [GitHub security reporting form][link-security] rather than the issue queue.

## Credits

- [Brian Faust][link-maintainer]
- [All Contributors][link-contributors]

## License

The MIT License. Please see [License File](LICENSE.md) for more information.

[ico-tests]: https://github.com/faustbrian/temporal/actions/workflows/quality-assurance.yaml/badge.svg
[ico-version]: https://img.shields.io/packagist/v/cline/temporal.svg
[ico-license]: https://img.shields.io/badge/License-MIT-green.svg
[ico-downloads]: https://img.shields.io/packagist/dt/cline/temporal.svg

[link-tests]: https://github.com/faustbrian/temporal/actions
[link-packagist]: https://packagist.org/packages/cline/temporal
[link-downloads]: https://packagist.org/packages/cline/temporal
[link-security]: https://github.com/faustbrian/temporal/security
[link-maintainer]: https://github.com/faustbrian
[link-contributors]: ../../contributors
