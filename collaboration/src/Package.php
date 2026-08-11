<?php

declare(strict_types=1);

namespace PeanutAdmin\Collaboration;

final class Package
{
    public const MODULE_KEY = 'peanut.collaboration';
    public const VERSION = '0.1.0-alpha.5';

    public const OPEN_OPERATION = 'collaboration.open-session';
    public const JOIN_OPERATION = 'collaboration.join-session';
    public const HEARTBEAT_OPERATION = 'collaboration.heartbeat';
    public const APPEND_OPERATION = 'collaboration.append-update';
    public const SNAPSHOT_OPERATION = 'collaboration.save-snapshot';
    public const STATE_OPERATION = 'collaboration.state';
    public const PUBLISH_OPERATION = 'collaboration.publish';
    public const CLOSE_OPERATION = 'collaboration.close-session';

    private function __construct() {}
}
