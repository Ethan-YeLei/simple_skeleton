<?php
declare(strict_types=1);

namespace Installer;

class Util
{
    public static function GetArrayItem(array $array, string $key, $default = null)
    {
        return isset($array[$key]) ? $array[$key] : $default;
    }

    public static function RecursiveRmdir(string $directory): void
    {
        if (! is_dir($directory)) {
            return;
        }
        $rdi = new \RecursiveDirectoryIterator($directory, \FilesystemIterator::SKIP_DOTS);
        $rii = new \RecursiveIteratorIterator($rdi, \RecursiveIteratorIterator::CHILD_FIRST);
        foreach ($rii as $filename => $fileInfo) {
            if ($fileInfo->isDir()) {
                rmdir($filename);
                continue;
            }
            unlink($filename);
        }
        rmdir($directory);
    }
}


