<?php

namespace Circli\Installer;

use Circli\Installer\Installers\ExtensionInstaller;
use Circli\Installer\Installers\ModuleInstaller;
use Composer\Composer;
use Composer\IO\IOInterface;
use Composer\Plugin\PluginInterface;
use Composer\EventDispatcher\EventSubscriberInterface;

class Plugin implements PluginInterface
{
    /**
     * Apply plugin modifications to Composer
     *
     * @param Composer $composer
     * @param IOInterface $io
     */
    public function activate(Composer $composer, IOInterface $io)
    {
        $extra = $composer->getPackage()->getExtra();

        $circliInstallers = [
            new ModuleInstaller($io, $composer),
            new ExtensionInstaller($io, $composer),
        ];
        if (isset($extra['moya']['plugins'])) {
            foreach ($extra['moya']['plugins'] as $installerClass) {
                $installer = new $installerClass($composer);
                $circliInstallers[] = $installer;
            }
        }

        $installationManager = $composer->getInstallationManager();
        $eventDispatcher = $composer->getEventDispatcher();

        foreach ($circliInstallers as $installer) {
            $installationManager->addInstaller($installer);
            if ($installer instanceof EventSubscriberInterface) {
                $eventDispatcher->addSubscriber($installer);
            }
        }
    }
}