# codeception-mountebank

[![Latest Version on Packagist][ico-version]][link-packagist]
[![Software License][ico-license]](LICENSE.md)
[![Build Status][ico-travis]][link-travis]
[![Coverage Status][ico-scrutinizer]][link-scrutinizer]
[![Quality Score][ico-code-quality]][link-code-quality]
[![Total Downloads][ico-downloads]][link-downloads]

codeception-mountebank provides [mountebank](http://www.mbtest.org/) integration with [Codeception](http://codeception.com/). Module allows to:

* Configure imposters required for tests run;
* Use imposters as mocks and verify specific requests were made;
* Save imposters after run for debugging purposes;

Module uses [Juggler](https://github.com/meare/juggler) to interact with mountebank and it's possible to take complete control on mountebank in your custom helper classes or modules.

Note that only HTTP imposters are currently supported.

## Install

Require module via Composer:

``` bash
$ composer require meare/codeception-mountebank
```

## Configuration

Module should be enabled and configured in suite configuration file

``` yaml
class_name: AcceptanceTester
modules:
    enabled:
        - Mountebank
    config:
        Mountebank:
            # mountebank host
            # Required
            host: 'localhost'

            # Port mountebank listens on
            # 2525 by default
            port: 2525

            # Imposters configuration
            # All previous imposters are deleted before the run
            imposters:

                # Imposter alias
                xyz:

                    # Path to imposter contract
                    # Required
                    contract: '_data/mountebank/xyz/stub.json'

                    # Set this property to save imposter contract after tests run
                    # Property value is path to save contract to
                    save: '_output/mountebank/xyz/stub.json'

                    # Set to true if imposter is used as mock
                    # Mock imposters are restored from original contract after each test
                    # Default: false
                    mock: true
```

## Usage

### Mock verification

**mountebank should be started with ``--mock`` flag to use mocking**

[&raquo; mountebank docs on mocking](http://www.mbtest.org/docs/api/mocks)

Imposters ``mock`` property should be set to ``true`` in suite configuration. It guarantees that imposter will be _restored_ before each test. _Restoring_ means deleting existing imposter from mountebank and posting contract from configuration. This is done to clean requests imposter recorded.

Module provides 3 methods to verify mock imposter:
#### ``seeImposterHasRequests($alias)``
Asserts that there was at least 1 request recorded on imposter
#### ``seeImposterHasRequestsByCriteria($alias, $criteria)``
Asserts that there was at least 1 request that satisfies criteria recorded on imposter.

If ``$criteria`` is array then request is considered matching if ``$criteria`` is subarray of request, e.g.:

``` php
$I->seeImposterHasRequestsByCriteria('xyz', [
  'method' => 'GET',
  'query' => [
    'account_id' => '7'
  ]
])
```

``` yaml
{
  "protocol": "http",
  "port": 4646,
  "numberOfRequests": 1,
  "name": "xyz",
  "requests": [
    {
      "requestFrom": "::ffff:127.0.0.1:57484",
      "method": "GET",
      "path": "/balance",
      "query": {
        "account_id": "7",
        "currency": "USD",
      },
      "headers": {
        "Host": "localhost",
        "Connection": "close"
      },
      "body": "",
      "timestamp": "2017-01-12T16:03:07.632Z"
    }
  ]
}

```

More complex criteria could be expressed as callback. Callback signature is:
```php
/**
 * @var string $request decoded request object from contract JSON.
 *
 * @return bool Whether requests matches
 */
function(array $request) {}
```
Callback will be called for each request imposter has until ``true`` is returned.

#### ``seeImposterHasNoRequests($alias)``
Asserts that there is no requests recorded on imposter.

## Change log

Please see [CHANGELOG](CHANGELOG.md) for more information on what has changed recently.

## Testing

``` bash
$ composer test
```

## Contributing

Please see [CONTRIBUTING](CONTRIBUTING.md) and [CONDUCT](CONDUCT.md) for details.

## Credits

- [Andrejs Mironovs][link-author]
- [All Contributors][link-contributors]

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.

[ico-version]: https://img.shields.io/packagist/v/meare/codeception-mountebank.svg?style=flat-square
[ico-license]: https://img.shields.io/badge/license-MIT-brightgreen.svg?style=flat-square
[ico-travis]: https://img.shields.io/travis/meare/codeception-mountebank/master.svg?style=flat-square
[ico-scrutinizer]: https://img.shields.io/scrutinizer/coverage/g/meare/codeception-mountebank.svg?style=flat-square
[ico-code-quality]: https://img.shields.io/scrutinizer/g/meare/codeception-mountebank.svg?style=flat-square
[ico-downloads]: https://img.shields.io/packagist/dt/meare/codeception-mountebank.svg?style=flat-square

[link-packagist]: https://packagist.org/packages/meare/codeception-mountebank
[link-travis]: https://travis-ci.org/meare/codeception-mountebank
[link-scrutinizer]: https://scrutinizer-ci.com/g/meare/codeception-mountebank/code-structure
[link-code-quality]: https://scrutinizer-ci.com/g/meare/codeception-mountebank
[link-downloads]: https://packagist.org/packages/meare/codeception-mountebank
[link-author]: https://github.com/meare
[link-contributors]: ../../contributors
