# Feature Flags for PHP 5.5
This SDK is designed to work with Split, the platform for controlled rollouts, serving features to your users via the Split feature flag to manage your complete customer experience.


## Getting started

### Running Locally
The repo comes with a PHP 5.5 docker container that includes xdebug and a handful of other nice to have PHP libraries/extensions enabled. 
To run this locally, the very first thing you would want to do is install the composer dependencies. This is accomplished with run a simple Make command:
```shell
$ make install
```

Once the composer dependencies have been installed you can run the unit tests with another Make command:  
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

## Split.io
Below is a simple example that describes the instantiation and most basic usage of our SDK.
Keep in mind that since PHP does not have the ability to share memory between processes the use of the [split-synchronizer](https://help.split.io/hc/en-us/articles/360019686092-Split-Synchronizer-Proxy) is mandatory for this SDK.

**IMPORTANT:** The Split SDK has a constraint of only being able to call the factory method once and get a valid return.
Because of this, we can only call `initializeSettings()` once, any further calls will be ignored.  

```php
<?php
use \Carsdotcom\FeatureFlags\Service\SplitIO\SplitFeatureFlag;
use \Carsdotcom\FeatureFlags\Service\SplitIO\SplitFeatureFlagUser;

try {
    $sdkConfig = [
        'log' => [
            'adapter' => 'syslog',
            'level' => 'verbose',
        ],
        'cache' => [
            'adapter' => 'predis',
            'parameters' => [
                'scheme' => 'tcp',
                'host' => 'REDIS_HOST',
                'port' => 'REDIS_PORT',
                'timeout' => 881,
            ],
            'options' => [
                'prefix' => 'development',
            ]
        ],
    ];
    
    $flags = SplitFeatureFlag::getInstance()
        ->initializeSettings(array_merge(['apiKey' => 'API_KEY', $sdkConfig))
        ->setUser(new SplitFeatureFlagUser($ccid = '123'));
       
    if ($flags->exists('my-new-feature') && $flags->enabled('my-new-feature')) {
        // flag exists and is turn on for this user
    else {
        // flag is either off OR doesn't exist
    }
    
} catch (\Carsdotcom\FeatureFlags\Exceptions\InvalidFeatureFlagUserException $exception) {
    // the user passed was invalid. This could happen if the user identifier wasn't sent during creation
} catch (\Carsdotcom\FeatureFlags\Exceptions\InvalidFeatureFlagException $exception) {
    // if the feature flag we called '$flags->enabled()' did not exist in the system, this exception is thrown 
}
```