<?php

namespace Carsdotcom\FeatureFlags\Contracts;

/**
 * Possible results of GateStateReader::gateState().
 */
final class GateState
{
    const ON = 'on';

    const OFF = 'off';

    /**
     * The gate could not be evaluated: there is no provider, it could not be asked, or its answer could not be
     * read. It does not mean the gate is off.
     */
    const UNAVAILABLE = 'unavailable';

    private function __construct()
    {
    }
}
