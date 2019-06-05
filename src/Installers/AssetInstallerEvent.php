<?php declare(strict_types=1);

namespace Circli\Installer\Installers;

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

        $types = ['styles', 'scripts', 'images'];
        foreach ($types as $type) {
            $typePath = $assetPath . '/' . $type;
            if (!file_exists($typePath)) {
                continue;
            }

            $method = 'link' . ucfirst($type);
            $this->$method($typePath, $name);
        }
    }

    private function linkScripts(string $path, string $linkName)
    {
        $target = realpath('assets/scripts/src/modules/') . '/' . $linkName;
        if (file_exists($target)) {
            return;
        }

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
}
