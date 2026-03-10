# Feature Flags for PHP 7.0
This SDK is designed to work with Split and Statsig.


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

```php
<?php

use \Carsdotcom\FeatureFlags\Service\Factory\FeatureFlagFactory;

try {
    // API keys are set up per environment. Be sure to use the correct apiKey/environment combo.
    $sdkConfig = [
        'apiKey' => 'API_KEY',
        'environment' => 'production', // 'development', 'staging', or 'production'
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

// will always return en empty array
$flags->config('foobar');
```