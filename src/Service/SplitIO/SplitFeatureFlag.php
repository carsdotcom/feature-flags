<?php

namespace Carsdotcom\FeatureFlags\Service\SplitIO;

use Carsdotcom\FeatureFlags\Contracts\FeatureFlag;
use Carsdotcom\FeatureFlags\Contracts\FeatureFlagUser;
use Carsdotcom\FeatureFlags\Exceptions\InvalidFeatureFlagException;
use Carsdotcom\FeatureFlags\Exceptions\InvalidFeatureFlagSettingsException;
use Carsdotcom\FeatureFlags\Exceptions\InvalidFeatureFlagUserException;
use SplitIO\Sdk;
use SplitIO\Sdk\ClientInterface;
use SplitIO\Sdk\Factory\SplitFactoryInterface;
use SplitIO\Sdk\Manager\SplitManagerInterface;

class SplitFeatureFlag implements FeatureFlag
{
    /**
     * @var SplitFactoryInterface
     */
    protected $factory;

    /**
     * @var ClientInterface
     */
    protected $client;

    /**
     * @var SplitManagerInterface
     */
    protected $manager;

    /**
     * @var FeatureFlagUser
     */
    protected $user;

    /**
     * @var SplitFeatureFlag
     */
    protected static $instance;

    public function __construct()
    {
        self::$instance = $this;
    }

    /**
     * @return SplitFeatureFlag
     */
    public static function getInstance()
    {
        if (is_null(self::$instance)) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    /**
     * @param $settings
     * @return void
     * @throws InvalidFeatureFlagSettingsException
     */
    public function initializeSettings($settings = [])
    {
        if (!is_null($this->factory)) {
            return;
        }

        $this->validateSettings($settings);

        $this->setFactory(Sdk::factory($settings['apiKey'], $settings));
        $this->setClient($this->factory->client());
        $this->setManager($this->factory->manager());

    }

    /**
     * @param array $settings
     * @return void
     * @throws InvalidFeatureFlagSettingsException
     */
    public function validateSettings($settings = [])
    {
        if (empty($settings['apiKey'])) {
            throw new InvalidFeatureFlagSettingsException;
        }
    }

    /**
     * @param SplitFactoryInterface $factory
     * @return $this
     */
    public function setFactory(SplitFactoryInterface $factory)
    {
        $this->factory = $factory;

        return $this;
    }

    /**
     * @param ClientInterface $client
     * @return $this
     */
    public function setClient(ClientInterface $client)
    {
        $this->client = $client;

        return $this;
    }

    /**
     * @param SplitManagerInterface $manager
     * @return $this
     */
    public function setManager(SplitManagerInterface $manager)
    {
        $this->manager = $manager;

        return $this;
    }

    /**
     * Will set the user the requested feature flags are evaluated for.
     *
     * @param FeatureFlagUser $user
     * @return self
     * @throws InvalidFeatureFlagUserException
     */
    public function setUser(FeatureFlagUser $user)
    {
        if (is_null($user->getId())) {
            throw new InvalidFeatureFlagUserException();
        }

        $this->user = $user;

        return $this;
    }

    /**
     * @return FeatureFlagUser
     * @throws InvalidFeatureFlagUserException
     */
    public function getUser()
    {
        if (is_null($this->user)) {
            throw new InvalidFeatureFlagUserException();
        }
        return $this->user;
    }

    /**
     * Return a list of all the available feature flag names
     *
     * @return array
     */
    public function all()
    {
        try{
            $flags = $this->manager->splits();
            if (empty($flags)) {
                return [];
            }

            return array_map(function($splits) {
                return $splits->getName();
            }, $flags);
        } catch (\Exception $exception) {
            // It is possible we get a redis search error here if no splits exist.
            // If so, catch that and return an empty array
            return [];
        }
    }

    /**
     * Will return true/false if the feature flag is enabled. if the feature flag doesn't exist this function
     * should throw InvalidFeatureFlagException
     *
     * @param String $featureFlagIdentifier
     * @return bool
     * @throws InvalidFeatureFlagException
     */

    /**
     * @param $featureFlagIdentifier
     * @return bool
     * @throws InvalidFeatureFlagUserException
     */
    public function enabled($featureFlagIdentifier)
    {
        $enabled = $this->client->getTreatment($this->getUser()->getId(), $featureFlagIdentifier);

        return $enabled === 'on';
    }

    /**
     * Will return true/false if the feature flag exists
     *
     * @param String $featureFlagIdentifier
     * @return bool
     */
    public function exists($featureFlagIdentifier)
    {
        return in_array($featureFlagIdentifier, $this->all());
    }

    /**
     * @param $featureFlagIdentifier
     * @return array
     * @throws InvalidFeatureFlagUserException
     */
    public function config($featureFlagIdentifier)
    {
        if (!$this->exists($featureFlagIdentifier)) {
            return [];
        }

        $flagData = $this->client->getTreatmentWithConfig($this->getUser()->getId(), $featureFlagIdentifier);

        // if no config has been defined in split it will return a null
        if (is_null($flagData['config'])) {
            return [];
        }

        return json_decode($flagData['config'], true);
    }
}