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

    /*
     * 命令执行完之后执行
     */
    public static function afterInstall(Event $event): void
    {
        $event->getIO()->write('<info> Clean componser.lock </info>');
        $lockFile = dirname(__DIR__) . "/composer.lock";
        if (is_file($lockFile)) {
            unlink($lockFile);
        }
    }
}



