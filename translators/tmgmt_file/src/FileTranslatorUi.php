<?php

/**
 * @file
 * Contains \Drupal\tmgmt_file\FileTranslatorUi:
 */

namespace Drupal\tmgmt_file;

use Drupal\Component\Utility\SafeMarkup;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StreamWrapper\StreamWrapperInterface;
use Drupal\tmgmt\JobInterface;
use Drupal\tmgmt\TranslatorInterface;
use Drupal\tmgmt\TranslatorPluginUiBase;
use GuzzleHttp\Stream\Stream;

/**
 * File translator UI.
 */
class FileTranslatorUi extends TranslatorPluginUiBase {

  /**
   * {@inheritdoc}
   */
  public function pluginSettingsForm(array $form, FormStateInterface $form_state, TranslatorInterface $translator, $busy = FALSE) {
    $form['export_format'] = array(
      '#type' => 'radios',
      '#title' => t('Export to'),
      '#options' => \Drupal::service('plugin.manager.tmgmt_file.format')->getLabels(),
      '#default_value' => $translator->getSetting('export_format'),
      '#description' => t('Please select the format you want to export data.'),
    );

    $form['xliff_processing'] = array(
      '#type' => 'checkbox',
      '#title' => t('Extended XLIFF processing'),
      '#description' => t('Check to further process content semantics and mask HTML tags instead just escaping it.'),
      '#default_value' => $translator->getSetting('xliff_processing'),
    );

    $form['allow_override'] = array(
      '#type' => 'checkbox',
      '#title' => t('Allow to override the format per job'),
      '#default_value' => $translator->getSetting('allow_override'),
    );

    // Any visible, writeable wrapper can potentially be used for the files
    // directory, including a remote file system that integrates with a CDN.
    foreach (\Drupal::service('stream_wrapper_manager')->getWrappers(StreamWrapperInterface::WRITE_VISIBLE) as $scheme => $info) {
      $stream = Stream::factory($info['class'], [$info['type']]);
      $options[$scheme] = SafeMarkup::checkPlain($stream->getMetadata('uri'));
    }

    if (!empty($options)) {
      $form['scheme'] = array(
        '#type' => 'radios',
        '#title' => t('Download method'),
        '#default_value' => $translator->getSetting('scheme'),
        '#options' => $options,
        '#description' => t('Choose the location where exported files should be stored. The usage of a protected location (e.g. private://) is recommended to prevent unauthorized access.'),
      );
    }

    return parent::pluginSettingsForm($form, $form_state, $translator);
  }

  /**
   * {@inheritdoc}
   */
  public function checkoutSettingsForm(array $form, FormStateInterface $form_state, JobInterface $job) {
    if ($job->getTranslator()->getSetting('allow_override')) {
      $form['export_format'] = array(
        '#type' => 'radios',
        '#title' => t('Export to'),
        '#options' => \Drupal::service('plugin.manager.tmgmt_file.format')->getLabels(),
        '#default_value' => $job->getTranslator()->getSetting('export_format'),
        '#description' => t('Please select the format you want to export data.'),
      );
    }
    return parent::checkoutSettingsForm($form, $form_state, $job);
  }

  /**
   * {@inheritdoc}
   */
  public function checkoutInfo(JobInterface $job) {
    // If the job is finished, it's not possible to import translations anymore.
    if ($job->isFinished()) {
      return parent::checkoutInfo($job);
    }
    $form = array(
      '#type' => 'fieldset',
      '#title' => t('Import translated file'),
    );

    $supported_formats = array_keys(\Drupal::service('plugin.manager.tmgmt_file.format')->getDefinitions());
    $form['file'] = array(
      '#type' => 'file',
      '#title' => t('File file'),
      '#size' => 50,
      '#description' => t('Supported formats: @formats.', array('@formats' => implode(', ', $supported_formats))),
    );
    $form['submit'] = array(
      '#type' => 'submit',
      '#value' => t('Import'),
      '#submit' => array('tmgmt_file_import_form_submit'),
    );
    return $this->checkoutInfoWrapper($job, $form);
  }

}
