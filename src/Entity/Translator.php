<?php

/**
 * @file
 * Contains Drupal\tmgmt\Plugin\Core\Entity\Translator.
 */

namespace Drupal\tmgmt\Entity;

use Drupal\Component\Plugin\Exception\PluginException;
use Drupal\Component\Utility\NestedArray;
use Drupal\Core\Cache\Cache;
use Drupal\Core\Config\Entity\ConfigEntityBase;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\tmgmt\JobInterface;
use Drupal\tmgmt\TranslatorInterface;

/**
 * Entity class for the tmgmt_translator entity.
 *
 * @ConfigEntityType(
 *   id = "tmgmt_translator",
 *   label = @Translation("Translator"),
 *   handlers = {
 *     "form" = {
 *       "edit" = "Drupal\tmgmt\Form\TranslatorForm",
 *       "add" = "Drupal\tmgmt\Form\TranslatorForm",
 *       "delete" = "Drupal\tmgmt\Form\TranslatorDeleteForm"
 *     },
 *     "list_builder" = "Drupal\tmgmt\Entity\ListBuilder\TranslatorListBuilder",
 *     "access" = "Drupal\tmgmt\Entity\Controller\TranslatorAccessControlHandler",
 *   },
 *   uri_callback = "tmgmt_translator_uri",
 *   config_prefix = "translator",
 *   entity_keys = {
 *     "id" = "name",
 *     "label" = "label",
 *     "weight" = "weight"
 *   },
 *   links = {
 *     "edit-form" = "/admin/config/regional/tmgmt_translator/manage/{tmgmt_translator}",
 *     "add-form" = "/admin/config/regional/tmgmt_translator/add",
 *     "delete-form" = "/tmgmt_translator/{tmgmt_translator}/delete",
 *   }
 * )
 *
 * @ingroup tmgmt_translator
 */
class Translator extends ConfigEntityBase implements TranslatorInterface {

  /**
   * Machine readable name of the translator.
   *
   * @var string
   */
  protected $name;

  /**
   * The UUID of this translator.
   *
   * @var string
   */
  protected $uuid;

  /**
   * Label of the translator.
   *
   * @var string
   */
  protected $label;

  /**
   * Description of the translator.
   *
   * @var string
   */
  protected $description;

  /**
   * Weight of the translator.
   *
   * @var int
   */
  protected $weight;

  /**
   * Plugin name of the translator.
   *
   * @type string
   */
  protected $plugin;

  /**
   * Translator type specific settings.
   *
   * @var array
   */
  protected $settings = array();

  /**
   * The supported target languages caches.
   *
   * @var array
   */
  protected $languageCache;

  /**
   * The supported language pairs caches.
   *
   * @var array
   */
  protected $languagePairsCache;

  /**
   * Whether the language cache in the database is outdated.
   *
   * @var boolean
   */
  protected $languageCacheOutdated;

  /**
   * {@inheritdoc}
   */
  public function id() {
    return $this->name;
  }

  /**
   * {@inheritdoc}
   */
  public function getSettings() {
    return $this->settings;
  }

  /**
   * {@inheritdoc}
   */
  public function setSettings(array $settings) {
    $this->settings = $settings;
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getSetting($name) {
    if (is_array($name)) {
      if (NestedArray::keyExists($this->settings, $name)) {
        return NestedArray::getValue($this->settings, $name);
      }
      elseif ($controller = $this->getPlugin()) {
        $defaults = $controller->defaultSettings();
        return NestedArray::getValue($defaults, $name);
      }
    }
    else {
      if (isset($this->settings[$name])) {
        return $this->settings[$name];
      }
      elseif ($controller = $this->getPlugin()) {
        $defaults = $controller->defaultSettings();
        if (isset($defaults[$name])) {
          return $defaults[$name];
        }
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function setSetting($setting_name, $value) {
    NestedArray::setValue($this->settings, (array) $setting_name, $value);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getPluginId() {
    return $this->plugin;
  }

  /**
   * {@inheritdoc}
   */
  public function getDescription() {
    return $this->description;
  }

  /**
   * {@inheritdoc}
   */
  public function setPluginID($plugin_id) {
    $this->plugin = $plugin_id;
  }

  /**
   * {@inheritdoc}
   */
  public static function preDelete(EntityStorageInterface $storage, array $entities) {
    // We are never going to have many entities here, so we can risk a loop.
    foreach ($entities as $key => $name) {
      if (tmgmt_translator_busy($key)) {
        // The translator can't be deleted because it is currently busy. Remove
        // it from the ids so it wont get deleted in the parent implementation.
        unset($entities[$key]);
      }
    }
    parent::preDelete($storage, $entities);
  }

  /**
   * {@inheritdoc}
   */
  public function getPlugin() {
    try {
      if (!empty($this->plugin)) {
        return \Drupal::service('plugin.manager.tmgmt.translator')->createInstance($this->plugin);
      }
    }
    catch (PluginException $e) {
    }
    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function getSupportedTargetLanguages($source_language) {
    if ($controller = $this->getPlugin()) {
      if (isset($this->pluginInfo['cache languages']) && empty($this->pluginInfo['cache languages'])) {
        // This plugin doesn't support language caching.
        return $controller->getSupportedTargetLanguages($this, $source_language);
      }
      else {
        // Retrieve the supported languages from the cache.
        if (empty($this->languageCache) && $cache = \Drupal::cache('data')->get('tmgmt_languages:' . $this->name)) {
          $this->languageCache = $cache->data;
        }
        // Even if we successfully queried the cache it might not have an entry
        // for our source language yet.
        if (!isset($this->languageCache[$source_language])) {
          $this->languageCache[$source_language] = $controller->getSupportedTargetLanguages($this, $source_language);
          $this->updateCache();
        }
      }
      return $this->languageCache[$source_language];
    }
  }

  /**
   * {@inheritdoc}
   */
  public function getSupportedLanguagePairs() {
    if ($controller = $this->getPlugin()) {
      if (isset($this->pluginInfo['cache languages']) && empty($this->pluginInfo['cache languages'])) {
        // This plugin doesn't support language caching.
        return $controller->getSupportedLanguagePairs($this);
      }
      else {
        // Retrieve the supported languages from the cache.
        if (empty($this->languagePairsCache) && $cache = \Drupal::cache('data')->get('tmgmt_language_pairs:' . $this->name)) {
          $this->languagePairsCache = $cache->data;
        }
        // Even if we successfully queried the cache data might not be yet
        // available.
        if (empty($this->languagePairsCache)) {
          $this->languagePairsCache = $controller->getSupportedLanguagePairs($this);
          $this->updateCache();
        }
      }
      return $this->languagePairsCache;
    }
  }

  /**
   * {@inheritdoc}
   */
  public function clearLanguageCache() {
    $this->languageCache = array();
    \Drupal::cache('data')->delete('tmgmt_languages:' . $this->name);
    \Drupal::cache('data')->delete('tmgmt_language_pairs:' . $this->name);
  }


  /**
   * {@inheritdoc}
   */
  public function canTranslate(JobInterface $job) {
    if ($controller = $this->getPlugin()) {
      return $controller->canTranslate($this, $job);
    }
    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function isAvailable() {
    if ($controller = $this->getPlugin()) {
      return $controller->isAvailable($this);
    }
    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function hasCheckoutSettings(JobInterface $job) {
    if ($controller = $this->getPlugin()) {
      return $controller->hasCheckoutSettings($job);
    }
    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function getNotAvailableReason() {
    if ($controller = $this->getPlugin()) {
      return $controller->getNotAvailableReason($this);
    }
    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function getNotCanTranslateReason(JobInterface $job) {
    if ($controller = $this->getPlugin()) {
      return $controller->getNotCanTranslateReason($job);
    }
    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function mapToRemoteLanguage($language) {
    return $this->getPlugin()->mapToRemoteLanguage($this, $language);
  }

  /**
   * {@inheritdoc}
   */
  public function mapToLocalLanguage($language) {
    return $this->getPlugin()->mapToLocalLanguage($this, $language);
  }

  /**
   * Updates the language cache.
   */
  protected function updateCache() {
    if ($controller = $this->getPlugin()) {
      $info = $controller->getPluginDefinition();
      if (!isset($info['language cache']) || !empty($info['language cache'])) {
        \Drupal::cache('data')->set('tmgmt_languages:' . $this->name, $this->languageCache, Cache::PERMANENT, $this->getEntityType()->getListCacheTags());
        \Drupal::cache('data')->set('tmgmt_language_pairs:' . $this->name, $this->languagePairsCache, Cache::PERMANENT, $this->getEntityType()->getListCacheTags());
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function providesRemoteLanguageMappings() {
    $definition = \Drupal::service('plugin.manager.tmgmt.translator')->getDefinition($this->getPluginId());
    if (!isset($definition['map_remote_languages'])) {
      return TRUE;
    }
    return $definition['map_remote_languages'];
  }

  /**
   * {@inheritdoc}
   */
  public function hasCustomSettingsHandling() {
    $definition = \Drupal::service('plugin.manager.tmgmt.translator')->getDefinition($this->getPluginId());

    if (isset($definition['job_settings_custom_handling'])) {
      return $definition['job_settings_custom_handling'];
    }

    return FALSE;
  }

}
