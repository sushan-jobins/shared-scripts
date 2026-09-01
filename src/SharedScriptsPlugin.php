<?php

namespace SushanJobins\SharedScripts;

use Composer\Composer;
use Composer\EventDispatcher\EventSubscriberInterface;
use Composer\IO\IOInterface;
use Composer\Plugin\PluginInterface;
use Composer\Script\Event;

class SharedScriptsPlugin implements PluginInterface, EventSubscriberInterface
{
    private ?Composer $composer = null;

    public function activate(Composer $composer, IOInterface $io): void
    {
        $this->composer = $composer;

        $io->write('<info>Shared Scripts plugin activated.</info>');
    }

    public function deactivate(Composer $composer, IOInterface $io): void
    {
        // Nothing to clean up.
    }

    public function uninstall(Composer $composer, IOInterface $io): void
    {
        // Nothing to clean up.
    }

    public static function getSubscribedEvents(): array
    {
        return [
            'post-install-cmd' => 'registerScripts',
            'post-update-cmd' => 'registerScripts',
        ];
    }

    public function registerScripts(Event $event): void
    {
        if ($this->composer === null) {
            return;
        }

        $package = $this->composer->getPackage();

        $scripts = $package->getScripts();

        $command = '@php vendor/sushan-jobins/shared-scripts/scripts/copy-missing-env.php';

        if (!isset($scripts['copy-missing-env'])) {
            $scripts['copy-missing-env'] = $command;

            $package->setScripts($scripts);

            $this->writeRootComposerJson($package->getScripts());

            $event->getIO()->write(
                '<info>Added "copy-missing-env" Composer command.</info>'
            );
        }
    }

    private function writeRootComposerJson(array $scripts): void
    {
        $config = $this->composer->getConfig();

        $composerFile = $config->getConfigSource();

        if ($composerFile === null) {
            return;
        }

        $composerPath = $composerFile->getName();

        if (!file_exists($composerPath)) {
            return;
        }

        $contents = file_get_contents($composerPath);

        if ($contents === false) {
            return;
        }

        $composerJson = json_decode($contents, true);

        if (!is_array($composerJson)) {
            return;
        }

        $composerJson['scripts'] = $scripts;

        file_put_contents(
            $composerPath,
            json_encode(
                $composerJson,
                JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES
            ).PHP_EOL
        );
    }
}