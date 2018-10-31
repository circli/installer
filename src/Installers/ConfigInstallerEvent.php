<?php

namespace Circli\Installer\Installers;

use Circli\Installer\PhpFile;
use Composer\Json\JsonFile;
use Composer\Package\PackageInterface;

class ConfigInstallerEvent
{
    public function __invoke(PackageInterface $package, string $packageDirectory, JsonFile $packageComposer)
    {
        if (!file_exists($packageDirectory. '/config')) {
            return;
        }

        [$packageNs, $name] = explode('/', $package->getName());
        if (!file_exists('config/'.$packageNs)) {
            /** @noinspection MkdirRaceConditionInspection */
            mkdir('config/'.$packageNs);
        }
        $configFile = new PhpFile('config/'.$package->getName().'.php');
        foreach (glob($packageDirectory . "config/*.php") as $filename) {
            $configFile->addInclude('../../' . $filename);
        }

        $configFile->replaceConfigMerge();
        $configFile->addReturnStatment('mergeConfig');
        $configFile->save();
    }
}
