<?php

namespace Circli\Installer\Installers;

use Composer\Installer\LibraryInstaller;
use Composer\Package\PackageInterface;

abstract class AbstractInstaller extends LibraryInstaller
{
    /**
     * Return the install path based on package type.
     *
     * @param string $installPath
     * @param  PackageInterface $package
     * @return string
     */
    public function buildInstallPath(string $installPath, PackageInterface $package): string
    {
        $type = $package->getType();

        $prettyName = $package->getPrettyName();
        if (str_contains($prettyName, '/')) {
            [$vendor, $name] = explode('/', $prettyName);
        } else {
            $vendor = '';
            $name = $prettyName;
        }

        $availableVars = $this->inflectPackageVars(compact('name', 'vendor', 'type'));
        return $this->templatePath($installPath, $availableVars);
    }

    /**
     * For an installer to override to modify the vars per installer.
     *
     * @param  array $vars
     * @return array
     */
    public function inflectPackageVars(array $vars): array
    {
        return $vars;
    }

    /**
     * Replace vars in a path
     *
     * @param  string $path
     * @param  mixed[]  $vars
     * @return string
     */
    protected function templatePath($path, array $vars = array()): string
    {
        if (str_contains($path, '{')) {
            extract($vars, \EXTR_SKIP);
            preg_match_all('@\{\$([A-Za-z0-9_]*)\}@i', $path, $matches);
            if (!empty($matches[1])) {
                foreach ($matches[1] as $var) {
                    $path = str_replace('{$' . $var . '}', $$var, $path);
                }
            }
        }

        return $path;
    }

    public function getConfigDirectory($real = true): string
    {
        if ($real) {
            return realpath('config/');
        }
        return 'config';
    }

    public function getLibraryDirectory($real = true): string
    {
        if ($real) {
            return realpath('extensions/');
        }
        return 'extensions';
    }

    public function getModuleDirectory($real = true): string
    {
        if ($real) {
            return realpath('modules/');
        }
        return 'modules';
    }
}
