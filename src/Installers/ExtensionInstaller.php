<?php

namespace Circli\Installer\Installers;

use Circli\Installer\PhpArrayFile;
use Composer\EventDispatcher\Event;
use Composer\EventDispatcher\EventSubscriberInterface;
use Composer\Installer\LibraryInstaller;
use Composer\Json\JsonFile;
use Composer\Package\PackageInterface;

class ExtensionInstaller extends AbstractInstaller implements EventSubscriberInterface
{
    public const PACKAGE_TYPE = 'circli-extension';
    public const INSTALL_PATH = 'extensions/{$name}/';

    /**
     * {@inheritDoc}
     */
    public function supports($packageType)
    {
        return $packageType === self::PACKAGE_TYPE;
    }

    /**
     * {@inheritDoc}
     */
    public function getInstallPath(PackageInterface $package): string
    {
        return $this->buildInstallPath(self::INSTALL_PATH, $package) ?: LibraryInstaller::getInstallPath($package);
    }

    /**
     * {@inheritDoc}
     */
    public static function getSubscribedEvents()
    {
        return [
            'post-install-cmd' => [
                ['updateExtension', 0]
            ],
            'post-update-cmd' => [
                ['updateExtension', 0]
            ],
        ];
    }

    public function updateExtension(Event $event)
    {
        if (!is_dir('config')) {
            return;
        }

        $extensionConfig = new PhpArrayFile('config/extensions.php');
        $packages = $this->composer->getRepositoryManager()->getLocalRepository()->getCanonicalPackages();
        $configEventHandler = new ConfigInstallerEvent();
        $assetEventHandler = new AssetInstallerEvent();

        foreach ($packages as $package) {
            if ($package->getType() !== self::PACKAGE_TYPE) {
                continue;
            }

            $installedPath = $this->getInstallPath($package);
            $packageComposerFile = new JsonFile($installedPath . 'composer.json');
            if (!$packageComposerFile->exists()) {
                continue;
            }
            $packageComposer = $packageComposerFile->read();
            $extension = $namespace = null;
            if (isset($packageComposer['autoload']['psr-4'])) {
                $namespace = rtrim(key($packageComposer['autoload']['psr-4']), '\\');
            }
            if ($namespace) {
                $extension = $namespace . '\Extension';
            }
            if (isset($packageComposer['extra']['circli']['extension'])) {
                $extension = $packageComposer['extra']['circli']['extension'];
            }
            if ($extension) {
                $extensionConfig[$package->getPrettyName()] = $extension;
            }

            $configEventHandler($package, $installedPath, $packageComposerFile);
            $assetEventHandler($package, $installedPath, $packageComposerFile);
        }
        $extensionConfig->save();
    }
}
