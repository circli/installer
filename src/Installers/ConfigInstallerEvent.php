<?php

namespace Circli\Installer\Installers;

use Circli\Installer\PhpArrayFile;
use Circli\Installer\PhpFile;
use Composer\Json\JsonFile;
use Composer\Package\PackageInterface;

class ConfigInstallerEvent
{
    public function __invoke(PackageInterface $package, string $packageDirectory, JsonFile $packageComposer): void
    {
        if (!file_exists($packageDirectory . '/config')) {
            return;
        }

        [$packageNs, $name] = explode('/', $package->getName());
        if (!file_exists('config/' . $packageNs)) {
            /** @noinspection MkdirRaceConditionInspection */
            mkdir('config/' . $packageNs);
        }
        $packageConfigPath = 'config/' . $package->getName();
        if (!file_exists($packageConfigPath)) {
            /** @noinspection MkdirRaceConditionInspection */
            mkdir($packageConfigPath);
        }
        $configFile = new PhpFile('config/' . $package->getName() . '.php');
        $files = [];
        foreach (glob($packageDirectory . 'config/*.php') as $filename) {
            $baseName = pathinfo($filename, PATHINFO_BASENAME);
            $files[] = $baseName;
            $target = realpath($packageConfigPath) . '/' . $baseName;

            if (is_link($target) && !file_exists($target)) {
                unlink($target);
            }

            if (!file_exists($target)) {
                if (defined('PHP_OS_FAMILY') && PHP_OS_FAMILY === 'Windows') {
                    $rs = @symlink(realpath($filename), $target);
                    if (!$rs) {
                        error_log('Failed to link config: ' . $filename);
                    }
                }
                else {
                    symlink('../../../' . $filename, $target);
                }
            }
            $configFile->addInclude('../../' . $filename);
        }

        $packageConfigOptions = new PhpArrayFile('config/available-configs.php');
        $packageConfigOptions[$package->getName()] = $files;
        $packageConfigOptions->save();

        $configFile->replaceConfigMerge();
        $configFile->addReturnStatement('mergeConfig');
        $configFile->save();
    }
}
