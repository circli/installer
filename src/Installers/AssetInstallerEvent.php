<?php declare(strict_types=1);

namespace Circli\Installer\Installers;

use Circli\Installer\PhpArrayFile;
use Composer\Json\JsonFile;
use Composer\Package\PackageInterface;

class AssetInstallerEvent
{
    public function __invoke(PackageInterface $package, string $packageDirectory, JsonFile $packageComposer)
    {
        $assetPath = $packageDirectory . 'assets';

        if (!file_exists($assetPath)) {
            return;
        }

        [$packageNs, $name] = explode('/', $package->getName());

        $validTypes = [];
        $types = ['styles', 'scripts', 'images', 'svg'];
        foreach ($types as $type) {
            $typePath = $assetPath . '/' . $type;
            if (!file_exists($typePath)) {
                continue;
            }

            $validTypes[] = $type;

            $method = 'link' . ucfirst($type);
            $this->$method($typePath, $name);
        }

        $assetConfig = new PhpArrayFile('config/assets.php');
        $assetConfig[$name] = $validTypes;
        $assetConfig->save();
    }

    private function linkScripts(string $path, string $linkName)
    {
        $target = realpath('assets/scripts/src/modules/') . '/' . $linkName;
        if (file_exists($target)) {
            return;
        }
        $modulesJson = [];
        $modulesFile = 'assets/scripts/modules.json';
        if (file_exists($modulesFile)) {
            $modulesJson = json_decode(file_get_contents($modulesFile), true);
        }

        if (file_exists($path . '/module.config.js')) {
            $modulesJson[$linkName] = $path . '/module.config.js';
        }
        else {
            $modulesJson[$linkName] = [
                'resolvePath' => $path . '/src',
            ];
        }

        file_put_contents($modulesFile, json_encode($modulesJson, JSON_PRETTY_PRINT));

        symlink(realpath($path . '/src'), $target);
    }

    private function linkStyles(string $path, string $linkName)
    {
        $target = realpath('assets/styles/modules/') . '/' . $linkName;
        if (file_exists($target)) {
            return;
        }

        symlink(realpath($path), $target);
    }

    private function linkImages(string $path, string $linkName)
    {
        $target = realpath('assets/images/') . '/' . $linkName;
        if (file_exists($target)) {
            return;
        }

        symlink(realpath($path), $target);
    }

    private function linkSvg(string $path, string $linkName)
    {
        $target = realpath('assets/svg/') . '/' . $linkName;
        if (file_exists($target)) {
            return;
        }

        symlink(realpath($path), $target);
    }
}
