# Feature Flags for PHP

This shared PHP library evaluates Statsig feature gates and dynamic configs. Gate and dynamic-config results are cached in Redis.

The Split implementation was removed in March 2026 and is no longer included. Composer supports PHP 7 and 8; the local test container uses PHP 7.0.


## Getting started

### Running Locally
The repo comes with a PHP 7.0 docker container that includes xdebug and a handful of other nice to have PHP libraries/extensions enabled. 

To run this locally, start by building the Docker image:
```shell
$ make build
```

You can then install the Composer dependencies:

```shell
$ make install
```

Once the composer dependencies have been installed, you can run the unit tests:  
```shell
$ make test
```

There are also Make commands to simply bring up/down the docker container and shell into the container:
```shell
$ make up
$ make shell
$ make down
```

### Example pseudo usage

```php
try {
    $user = new FeatureFlagUser('dealer@dealerinspire.com');
    $flags = (new FeatureFlag())
        ->setUser($user);
       
    if ($flags->exists('my-new-feature')) {
        if ($flags->enabled('my-new-feature')) {
            // feature exists and is turned on for this user
        } else {
            // feature is either turned off for this user
        }
    } else {
        // the feature flag does not exist yet
    }
    
} catch (\Carsdotcom\FeatureFlags\Exceptions\InvalidFeatureFlagUserException $exception) {
    // the user passed was invalid. This could happen if the user identifier wasn't sent during creation
} catch (\Carsdotcom\FeatureFlags\Exceptions\InvalidFeatureFlagException $exception) {
    // if the feature flag we called '$flags->enabled()' did not exist in the system, this exception is thrown 
}
```

## Statsig

Below is an example of using the SDK for Statsig. Note that in Statsig, "feature flags" are called "feature gates":

Uncached gate evaluations use a five-second HTTP timeout by default. Set `gateTimeout` to a positive number of seconds (including fractions such as `0.5`) in the SDK config to use a different limit for a consumer. Other Statsig API requests are not affected.

```php
<?php

use \Carsdotcom\FeatureFlags\Service\Factory\FeatureFlagFactory;

try {
    // API keys are set up per environment. Be sure to use the correct apiKey/environment combo.
    $sdkConfig = [
        'apiKey' => 'API_KEY',
        'environment' => 'production', // 'development', 'staging', or 'production'
        'gateTimeout' => 1,
        'cache' => [
            'scheme' => 'tcp', // 'tcp' or 'tls'
            'host' => '127.0.0.1',
            'port' => 6379,
            'password' => '',
            'prefix' => 'flags',
        ]
    ];
    
    // CCID is used for user targeting and segments.
    $userId = '123';

    // Use the FeatureFlagFactory for instantiation- not the StatsigFeatureFlag class directly.
    // If we migrate feature flag providers again, we only have to change the factory.
    $flags = FeatureFlagFactory::create($sdkConfig, $userId);

    // Check if a feature flag (gate) is enabled for the current user
    if ($flags->enabled('my-new-feature')) {
        // Calling $flags->exists() before $flags->enabled() is not needed due to our Statsig implementation.
        // Doing so adds unnecessary overhead.
        // Calling $flags->enabled() on a non-existent flag returns false.
    }

    // Check if a feature flag (gate) name exists
    if ($flags->exists('my-new-feature')) {
        // ...
    }
    
    // Get all available feature flags (gates) names
    $allFlags = $flags->all();
    
    // Change the user (uncommon use case)
    // Again, note the use of FeatureFlagFactory instead of the StatsigFeatureFlagUser class.
    $flags->setUser(FeatureFlagFactory::createUser('some-other-CCID'));
    
} catch (\Carsdotcom\FeatureFlags\Exceptions\InvalidFeatureFlagUserException $exception) {
    // the user passed was invalid. This could happen if the user identifier wasn't sent during creation
} catch (\Carsdotcom\FeatureFlags\Exceptions\InvalidFeatureFlagException $exception) {
    // if the feature flag we called '$flags->enabled()' did not exist in the system, this exception is thrown 
}
```

### Telling "off" apart from "could not be evaluated"

`enabled()` returns `false` both when a gate is off and when Statsig could not be asked (timeout, network error, non-200 response, or a body without a boolean `value`). That is the right answer for hiding a feature, but not for code that deletes data when a gate is off. Such callers create the service with `FeatureFlagFactory::createGateStateReader($sdkConfig, $userId)`, which returns the same Statsig service as `create()` typed as `GateStateReader`, and call `gateState()`. It returns one of three `GateState` constants:
- `GateState::ON`: the gate is on for this user.
- `GateState::OFF`: Statsig answered that the gate is off, or the gate does not exist.
- `GateState::UNAVAILABLE`: the gate could not be evaluated; do not treat it as off.

Caching:
- An answer from Statsig (on or off) is cached for 5 minutes, and a failed check for 1 minute, so the next call after that asks Statsig again. This applies to `enabled()` and `gateState()`.
- `gateState()` results are cached under their own keys (`gate_state::<gate>::<user>`). The `enabled()` keys keep holding only booleans, so consumers on older versions of this library sharing the same cache are not affected.
- `enabled()` and `gateState()` do not share cache entries, so calling both for the same gate and user can make two Statsig requests in the same window.
- If the cache cannot be read or written, `gateState()` asks Statsig directly instead of failing, so with both Redis and Statsig down every call waits for the gate timeout (5 seconds unless `gateTimeout` is set). Callers that cannot tolerate that latency should use `enabled()`.

## Null Feature Flag
This service will **always** return as if there are no feature flags enabled/exist and never throw any exceptions.

```php
use \Carsdotcom\FeatureFlags\Service\Null\NullFeatureFlag;
use \Carsdotcom\FeatureFlags\Service\Null\NullFeatureFlagUser;

$flags = new NullFeatureFlag();

// any value can be sent to the NullFeatureFlagUser to instantiate it
$flags->setUser(new NullFeatureFlagUser(null));

// will always return an empty array
$flags->all();

// will always return false
$flags->exists('foobar');

// will always return false
$flags->enabled('foobar');

// will always return GateState::UNAVAILABLE, since no gate can be evaluated without a provider
$flags->gateState('foobar');

// will always return en empty array
$flags->config('foobar');
```
