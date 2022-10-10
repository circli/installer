<?php declare(strict_types=1);

namespace Circli\Installer\Installers;

use Circli\Installer\PhpArrayFile;
use Composer\EventDispatcher\Event;
use Composer\EventDispatcher\EventSubscriberInterface;
use Composer\Installer\LibraryInstaller;
use Composer\Json\JsonFile;
use Composer\Package\PackageInterface;

class ModuleInstaller extends AbstractInstaller implements EventSubscriberInterface
{
    public const PACKAGE_TYPE = 'circli-module';
    public const INSTALL_PATH = 'modules/{$name}/';

    /**
     * {@inheritDoc}
     */
    public function supports(string $packageType): bool
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
    public static function getSubscribedEvents(): array
    {
        return [
            'post-install-cmd' => [
                ['updateModules', 0]
            ],
            'post-update-cmd' => [
                ['updateModules', 0]
            ],
        ];
    }

    public function updateModules(Event $event): void
    {
        if (!is_dir('config')) {
            return;
        }

        $moduleConfig = new PhpArrayFile('config/modules.php');
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

            $module = $namespace = null;
            if (isset($packageComposer['autoload']['psr-4'])) {
                $namespace = rtrim(key($packageComposer['autoload']['psr-4']), '\\');
            }
            if ($namespace) {
                $module = $namespace . '\\Module';
            }

            if (isset($packageComposer['extra']['circli']['module'])) {
                $module = $packageComposer['extra']['circli']['module'];
            }
            if ($module) {
                $moduleConfig[] = $module;
            }

            $configEventHandler($package, $installedPath, $packageComposerFile);
            $assetEventHandler($package, $installedPath, $packageComposerFile);
        }
        $moduleConfig->save();
    }
}
