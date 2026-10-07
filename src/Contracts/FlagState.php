<?php

namespace Carsdotcom\FeatureFlags\Contracts;

/**
 * Possible results of FeatureFlag::getFlagState().
 */
final class FlagState
{
    const ON = 'on';

    const OFF = 'off';

    /**
     * The flag could not be evaluated: there is no provider, it could not be asked, or its answer could not be
     * read. It does not mean the flag is off.
     */
    const UNAVAILABLE = 'unavailable';

    private function __construct()
    {
    }
}
