<?php

namespace Circli\Installer;

use Circli\Installer\Installers\ExtensionInstaller;
use Circli\Installer\Installers\ModuleInstaller;
use Composer\Composer;
use Composer\EventDispatcher\EventSubscriberInterface;
use Composer\Installer\InstallerInterface;
use Composer\IO\IOInterface;
use Composer\Plugin\PluginInterface;

class Plugin implements PluginInterface
{
    /** @var InstallerInterface[] */
    private $installers = [];

    /**
     * Apply plugin modifications to Composer
     *
     * @param Composer $composer
     * @param IOInterface $io
     */
    public function activate(Composer $composer, IOInterface $io)
    {
        $extra = $composer->getPackage()->getExtra();

        $this->installers = [
            new ModuleInstaller($io, $composer),
            new ExtensionInstaller($io, $composer),
        ];
        if (isset($extra['moya']['plugins'])) {
            foreach ($extra['moya']['plugins'] as $installerClass) {
                $installer = new $installerClass($composer);
                $this->installers[] = $installer;
            }
        }

        $installationManager = $composer->getInstallationManager();
        $eventDispatcher = $composer->getEventDispatcher();

        foreach ($this->installers as $installer) {
            $installationManager->addInstaller($installer);
            if ($installer instanceof EventSubscriberInterface) {
                $eventDispatcher->addSubscriber($installer);
            }
        }
    }

    public function deactivate(Composer $composer, IOInterface $io)
    {
        $installationManager = $composer->getInstallationManager();
        foreach ($this->installers as $installer) {
            $installationManager->removeInstaller($installer);
        }
    }

    public function uninstall(Composer $composer, IOInterface $io)
    {
    }
}
