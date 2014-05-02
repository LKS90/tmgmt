<?php

/**
 * @file
 * Contains Drupal\tmgmt\TranslatorPluginBase.
 */

namespace Drupal\tmgmt;

use Drupal\Component\Plugin\PluginBase;
use Drupal\tmgmt\Entity\Job;
use Drupal\tmgmt\Entity\JobItem;
use Drupal\tmgmt\Entity\Translator;

/**
 * Default controller class for service plugins.
 *
 * @ingroup tmgmt_translator
 */
abstract class TranslatorPluginBase extends PluginBase implements TranslatorPluginInterface {

  protected $supportedRemoteLanguages = array();
  protected $remoteLanguagesMappings = array();

  /**
   * Characters that indicate the beginning of an escaped string.
   *
   * @var string
   */
  protected $escapeStart = '';

  /**
   * Characters that indicate the end of an escaped string.
   *
   * @var string
   */
  protected $escapeEnd = '';

  /**
   * {@inheritdoc}
   */
  public function isAvailable(Translator $translator) {
    // Assume that the translation service is always available.
    return TRUE;
  }

  /**
   * {@inheritdoc}
   */
  public function canTranslate(Translator $translator, Job $job) {
    // The job is only translatable if the translator is available too.
    if ($this->isAvailable($translator) && array_key_exists($job->target_language, $translator->getSupportedTargetLanguages($job->source_language))) {
      // We can only translate this job if the target language of the job is in
      // one of the supported languages.
      return TRUE;
    }
    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function abortTranslation(Job $job) {
    // Assume that we can abort a translation job at any time.
    $job->aborted();
    return TRUE;
  }

  /**
   * {@inheritdoc}
   */
  public function getDefaultRemoteLanguagesMappings() {
    return array();
  }

  /**
   * {@inheritdoc}
   */
  public function getSupportedRemoteLanguages(Translator $translator) {
    return array();
  }

  /**
   * {@inheritdoc}
   */
  public function getRemoteLanguagesMappings(Translator $translator) {
    if (!empty($this->remoteLanguagesMappings)) {
      return $this->remoteLanguagesMappings;
    }

    foreach (language_list() as $language => $info) {
      $this->remoteLanguagesMappings[$language] = $this->mapToRemoteLanguage($translator, $language);
    }

    return $this->remoteLanguagesMappings;
  }

  /**
   * {@inheritdoc}
   */
  public function mapToRemoteLanguage(Translator $translator, $language) {
    if (!tmgmt_provide_remote_languages_mappings($translator)) {
      return $language;
    }

    if (isset($translator->settings['remote_languages_mappings'][$language])) {
      return $translator->settings['remote_languages_mappings'][$language];
    }

    $default_mappings = $this->getDefaultRemoteLanguagesMappings();

    if (isset($default_mappings[$language])) {
      return $default_mappings[$language];
    }

    return $language;
  }

  /**
   * {@inheritdoc}
   */
  public function mapToLocalLanguage(Translator $translator, $language) {
    if (!tmgmt_provide_remote_languages_mappings($translator)) {
      return $language;
    }

    if (isset($translator->settings['remote_languages_mappings']) && is_array($translator->settings['remote_languages_mappings'])) {
      $mappings = $translator->settings['remote_languages_mappings'];
    }
    else {
      $mappings = $this->getDefaultRemoteLanguagesMappings();
    }

    if ($remote_language = array_search($language, $mappings)) {
      return $remote_language;
    }

    return $language;
  }


  /**
   * {@inheritdoc}
   */
  public function getSupportedTargetLanguages(Translator $translator, $source_language) {
    $languages = entity_metadata_language_list();
    unset($languages[LANGUAGE_NONE], $languages[$source_language]);
    return drupal_map_assoc(array_keys($languages));
  }

  /**
   * {@inheritdoc}
   *
   * Default implementation that gets target languages for each remote language.
   * This approach is ineffective and therefore it is advised that a plugin
   * should provide own implementation.
   */
  public function getSupportedLanguagePairs(Translator $translator) {
    $language_pairs = array();

    foreach ($this->getSupportedRemoteLanguages($translator) as $source_language) {
      foreach ($this->getSupportedTargetLanguages($translator, $source_language) as $target_language) {
        $language_pairs[] = array('source_language' => $source_language, 'target_language' => $target_language);
      }
    }

    return $language_pairs;
  }


  /**
   * {@inheritdoc}
   */
  public function getNotCanTranslateReason(Job $job) {
    return t('@translator can not translate from @source to @target.', array('@translator' => $job->getTranslator()->label(), '@source' => \Drupal::languageManager()->getLanguage($job->source_language)->getName(), '@target' => \Drupal::languageManager()->getLanguage($job->target_language)->getName()));
  }

  /**
   * {@inheritdoc}
   */
  public function getNotAvailableReason(Translator $translator) {
    return t('@translator is not available. Make sure it is properly !configured.', array('@translator' => $this->pluginDefinition['label'], '!configured' => l(t('configured'), 'admin/config/regional/tmgmt_translator/manage/' . $translator->name)));
  }

  /**
   * {@inheritdoc}
   */
  public function defaultSettings() {
    $defaults = array('auto_accept' => FALSE);
    // Check if any default settings are defined in the plugin info.
    if (isset($this->pluginDefinition['default_settings'])) {
      return array_merge($defaults, $this->pluginDefinition['default_settings']);
    }
    return $defaults;
  }

  /**
   * {@inheritdoc}
   */
  public function hasCheckoutSettings(Job $job) {
    return TRUE;
  }

  /**
   * {@inheritdoc}
   */
  public function acceptedDataItem(JobItem $job_item, array $key) {
    return TRUE;
  }

  /**
   * {@inheritdoc}
   */
  public function escapeText(array $data_item) {
    if (empty($data_item['#escape'])) {
      return $data_item['#text'];
    }

    $text = $data_item['#text'];
    $escape = $data_item['#escape'];

    // Sort them in reverse order based/ on the position and process them,
    // so that positions don't change.
    krsort($escape, SORT_NUMERIC);

    foreach ($escape as $position => $info) {
      $text = substr_replace($text, $this->getEscapedString($info['string']), $position, strlen($info['string']));
    }

    return $text;
  }

  /**
   * Returns the escaped string.
   *
   * Defaults to use the escapeStart and escapeEnd properties but can be
   * overriden if a non-static replacement pattern is used.
   *
   * @param string $string
   *   String that should be escaped.
   * @return string
   */
  protected function getEscapedString($string) {
    return $this->escapeStart . $string . $this->escapeEnd;
  }

  /**
   * {@inheritdoc}
   */
  public function unescapeText($text) {
    return preg_replace('/' . preg_quote($this->escapeStart, '/') . '(.+)' . preg_quote($this->escapeEnd, '/') . '/U', '$1', $text);
  }

}

