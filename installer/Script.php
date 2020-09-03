<?php

declare(strict_types=1);

namespace Installer;


use Composer\Script\Event;

class Script
{

    public static function install(Event $event): void
    {
        $installer = new InstallMain($event->getIO(), $event->getComposer());
        $installer->run();
    }
}



