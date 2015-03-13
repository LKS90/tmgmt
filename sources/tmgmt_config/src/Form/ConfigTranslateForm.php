<?php

/**
 * @file
 * Contains \Drupal\tmgmt_config\Form\ConfigTranslateForm.
 */

namespace Drupal\tmgmt_config\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Language\LanguageInterface;
use Drupal\Core\Url;
use Drupal\tmgmt\Entity\JobItem;
use Drupal\tmgmt\TMGMTException;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\config_translation\ConfigMapperManagerInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * Configuration translation overview form.
 */
class ConfigTranslateForm extends FormBase {

  /**
   * The configuration mapper manager.
   *
   * @var \Drupal\config_translation\ConfigMapperManagerInterface
   */
  protected $configMapperManager;

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'tmgmt_content_translate_form';
  }

  /**
   * Constructs a ConfigTranslationController.
   *
   * @param \Drupal\config_translation\ConfigMapperManagerInterface $config_mapper_manager
   *   The configuration mapper manager.
   */
  public function __construct(ConfigMapperManagerInterface $config_mapper_manager) {
    $this->configMapperManager = $config_mapper_manager;
  }

  /**
   * @param \Symfony\Component\DependencyInjection\ContainerInterface $container
   * @return static
   */
  public static function create(ContainerInterface $container) {
    return new static($container->get('plugin.manager.config_translation.mapper'));
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, Request $request = NULL, array $build = NULL, $plugin_id = NULL) {
    // Store the entity in the form state so we can easily create the job in the
    // submit handler.

    /** @var \Drupal\config_translation\ConfigMapperInterface $mapper */
    $mapper = $this->configMapperManager->createInstance($plugin_id);
    $mapper->populateFromRequest($request);

    $form_state->set('mapper', $mapper);
    $form_state->set('plugin_id', $plugin_id);

    // @todo: Support more than one config names.
    if (count($mapper->getConfigNames()) > 1) {
      drupal_set_message(t('There are more than one config names.'), 'warning');
    }
    $id = $mapper->getConfigNames()[0];
    $form_state->set('id', $id);

    $form['#title'] = $this->t('Translations of @title', array('@title' => $mapper->getTitle()));
    $overview = $build['languages'];

    $form['top_actions']['#type'] = 'actions';
    $form['top_actions']['#weight'] = -10;
    tmgmt_add_cart_form($form['top_actions'], $form_state, 'config', $plugin_id, $id);

    // Inject our additional column into the header.
    array_splice($overview['#header'], -1, 0, array(t('Pending Translations')));
    // Make this a tableselect form.
    $form['languages'] = array(
      '#type' => 'tableselect',
      '#header' => $overview['#header'],
      '#options' => array(),
    );
    $languages = \Drupal::languageManager()->getLanguages();
    // Check if there is a job / job item that references this translation.
    $items = tmgmt_job_item_load_latest('config', $plugin_id, $id, $mapper->getLangcode());
    foreach ($languages as $langcode => $language) {
      if ($langcode == LanguageInterface::LANGCODE_DEFAULT) {
        // Never show language neutral on the overview.
        continue;
      }
      // Since the keys are numeric and in the same order we can shift one element
      // after the other from the original non-form rows.
      $option = $overview[$langcode];
      if ($langcode == $mapper->getLangcode()) {
        $additional = '<strong>' . t('Source') . '</strong>';
        // This is the source object so we disable the checkbox for this row.
        $form['languages'][$langcode] = array(
          '#type' => 'checkbox',
          '#disabled' => TRUE,
        );
      }
      elseif (isset($items[$langcode])) {
        $item = $items[$langcode];
        $states = JobItem::getStates();
        $additional = \Drupal::l($states[$item->getState()], $item->urlInfo()->setOption('query', array('destination' => Url::fromRoute('<current>')->getInternalPath())));
        // Disable the checkbox for this row since there is already a translation
        // in progress that has not yet been finished. This way we make sure that
        // we don't stack multiple active translations for the same item on top
        // of each other.
        $form['languages'][$langcode] = array(
          '#type' => 'checkbox',
          '#disabled' => TRUE,
        );
      }
      else {
        // There is no translation job / job item for this target language.
        $additional = t('None');
      }
      // Inject the additional column into the array.

      // The generated form structure has changed, support both an additional
      // 'data' key (that is not supported by tableselect) and the old version
      // without.
      if (isset($option['data'])) {
        array_splice($option['data'], -1, 0, array($additional));
        // Append the current option array to the form.
        $form['languages']['#options'][$langcode] = $option['data'];
      }
      else {
        array_splice($option, -1, 0, array($additional));
        // Append the current option array to the form.
        $form['languages']['#options'][$langcode] = array(
          drupal_render($option['language']),
          $additional,
          drupal_render($option['operations']),
        );
      }
    }
    $form['actions']['#type'] = 'actions';
    $form['actions']['request'] = array(
      '#type' => 'submit',
      '#value' =>$this->t('Request translation'),
      '#validate' => array('::validateForm'),
      '#submit' => array('::submitForm'),
    );
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  function validateForm(array &$form, FormStateInterface $form_state) {
    $selected = array_filter($form_state->getValue('languages'));
    if (empty($selected)) {
      $form_state->setErrorByName('languages', $this->t('You have to select at least one language for requesting a translation.'));
    }
  }

  /**
   * {@inheritdoc}
   */
  function submitForm(array &$form, FormStateInterface $form_state) {
    /** @var \Drupal\config_translation\ConfigMapperManagerInterface $mapper */
    $mapper = $form_state->get('mapper');
    $values = $form_state->getValues();
    $jobs = array();
    foreach (array_keys(array_filter($values['languages'])) as $langcode) {
      // Create the job object.
      $job = tmgmt_job_create($mapper->getLangcode(), $langcode, \Drupal::currentUser()->id());
      try {
        // Add the job item.
        $job->addItem('config', $form_state->get('plugin_id'), $form_state->get('id'));
        // Append this job to the array of created jobs so we can redirect the user
        // to a multistep checkout form if necessary.
        $jobs[$job->id()] = $job;
      }
      catch (TMGMTException $e) {
        watchdog_exception('tmgmt', $e);
        $languages = \Drupal::languageManager()->getLanguages();
        $target_lang_name = $languages[$langcode]->language;
        drupal_set_message(t('Unable to add job item for target language %name. Make sure the source content is not empty.', array('%name' => $target_lang_name)), 'error');
      }
    }
    tmgmt_job_checkout_and_redirect($form_state, $jobs);
  }

}