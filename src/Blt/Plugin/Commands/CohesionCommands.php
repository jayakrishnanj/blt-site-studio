<?php

namespace Acquia\Cohesion\Blt\Plugin\Commands;

use Acquia\Blt\Robo\BltTasks;
use Acquia\Blt\Robo\Exceptions\BltException;
use Consolidation\AnnotatedCommand\CommandData;
use Symfony\Component\Console\Event\ConsoleCommandEvent;
use Robo\Contract\VerbosityThresholdInterface;

/**
 * Defines commands related to Site Studio.
 */
class CohesionCommands extends BltTasks {

  /**
   * @hook post-command drupal:config:import
   */
  public function postCommand($result, CommandData $commandData)
  {
    $result = $this->taskDrush()
      ->stopOnFail()
      ->drush("pm:list --filter=\"cohesion_sync\" --status=Enabled --field=status")
      ->run();

    if(trim($result->getMessage()) === "Enabled") {
      // Rebuild cache.
      $result = $this->taskDrush()
        ->stopOnFail()
        ->alias("self")
        ->drush("cr")
        ->run();

      // Import Site Studio assets from the API.
      $result = $this->taskDrush()
        ->stopOnFail()
        ->drush("cohesion:import")
        ->run();

      // Import Site Studio configuration from the sync folder.
      $result = $this->taskDrush()
        ->stopOnFail()
        ->drush("sync:import --overwrite-all --force")
        ->run();

      // Rebuild Site Studio.
      $result = $this->taskDrush()
        ->stopOnFail()
        ->drush("cohesion:rebuild")
        ->run();

      // Is the site in maintenance mode?
      $result = $this->taskDrush()
        ->stopOnFail()
        ->drush("state:get system.maintenance_mode")
        ->run();

      if(trim($result->getMessage()) === '1') {
        // Take the site out of maintenance mode.
        $result = $this->taskDrush()
          ->stopOnFail()
          ->alias("self")
          ->drush("state:set system.maintenance_mode 0 --input-format=integer")
          ->run();
      }
    } else {
      $this->say("Site Studio sync is not enabled. Skipping Site Studio import and rebuild.");
    }
  }

  /**
   * Initializes default Site Studio config split configuration for this project.
   *
   * @command recipes:config:init:sitestudio
   * @throws \Acquia\Blt\Robo\Exceptions\BltException
   */
  public function generateSiteStudioConifg() {
    $split_dir = $this->getConfigValue('repo.root') ."/config/site_studio_sync";
    $this->say("This command will automatically generate and place configuration and settings files for Site Studio.");
    $result = $this->taskFilesystemStack()
      ->mkdir($split_dir)
      ->run();
    if (!$result->wasSuccessful()) {
      throw new BltException("Unable to create $split_dir.");
    }
    $result = $this->taskFilesystemStack()
      ->copy($this->getConfigValue('repo.root') . '/vendor/acquia/blt-site-studio/config/config_split.config_split.site_studio_ignored_config.yml', $this->getConfigValue('repo.root') . '/config/default/config_split.config_split.site_studio_ignored_config.yml', TRUE)
      ->copy($this->getConfigValue('repo.root') . '/vendor/acquia/blt-site-studio/config/config_ignore.settings.yml', $this->getConfigValue('repo.root') . '/config/default/config_ignore.settings.yml', TRUE)
      ->copy($this->getConfigValue('repo.root') . '/vendor/acquia/blt-site-studio/config/.htaccess', $this->getConfigValue('repo.root') . '/config/site_studio_sync/.htaccess', TRUE)
      ->copy($this->getConfigValue('repo.root') . '/vendor/acquia/blt-site-studio/config/README.md', $this->getConfigValue('repo.root') . '/config/site_studio_sync/README.md', TRUE)
      ->copy($this->getConfigValue('repo.root') . '/vendor/acquia/blt-site-studio/settings/includes.settings.php', $this->getConfigValue('docroot') . 'sites/default/config/default/settings/includes.settings.php', TRUE)
      ->copy($this->getConfigValue('repo.root') . '/vendor/acquia/blt-site-studio/settings/site-studio.settings.php', $this->getConfigValue('docroot') . 'sites/default/config/default/settings/site-studio.settings.php', TRUE)
      ->stopOnFail()
      ->setVerbosityThreshold(VerbosityThresholdInterface::VERBOSITY_VERBOSE)
      ->run();

    if (!$result->wasSuccessful()) {
      throw new BltException("Could not initialize Site Studio configuration.");
    }

  }

}
