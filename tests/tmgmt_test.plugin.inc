<?php

/**
 * @file
 * Provides the user translator plugin controller.
 */

class TMGMTTestTranslatorPluginController extends TMGMTDefaultTranslatorPluginController {

  /**
   * Implements TMGMTTranslatorPluginControllerInterface::requestTranslation().
   */
  function requestTranslation(TMGMTJob $job) {
    // Do something different based on the target language.
    switch ($job->target_language) {
      case 'de':
        $job->state = TMGMTJob::STATE_SUBMITTED;

        break;

      case 'es':
        $job->state = TMGMTJob::STATE_REVIEW;

        foreach ($job->getItems() as $item) {
          /* @var $item TMGMTJobItem */
          $data = tmgmt_flatten_data($item->getSourceData());
          foreach ($data as $key => $data_entry) {
            // Translate into spanish!

            $data[$key]['#text'] = str_replace('n', 'ñ', $data_entry['#text']);
          }
          $item->translated_data = tmgmt_unflatten_data($data);
          $item->save();
        }
        break;

      case 'it':
        $job->state = TMGMTJob::STATE_REJECTED;
        $job->response = 'Translation into italian not supported';
        break;

      case 'zh-hans':
        // Target not reachable.
        return FALSE;
        break;
    }

    // @todo: Who nees to save the job?
    $job->save();
  }
}

class TMGMTTestSourcePluginController extends TMGMTDefaultSourcePluginController {

  public function getData(TMGMTJobItem $job_item) {
    return array(
      'dummy' => array(
        'deep_nesting' => array(
          '#text' => 'Text for job item with type ' . $job_item->item_type . ' and id ' . $job_item->item_id . '.',
        ),
      ),
    );
  }

  public function saveTranslation(TMGMTJobItem $job_item) {
    // Set a variable that can be checked later for a given job item.
    variable_set('tmgmt_test_saved_translation_' . $job_item->item_type . '_' . $job_item->item_id, TRUE);
  }
}

class TMGMTTestTranslatorResponse implements TMGMTranslatorResponseInterface {

  /**
   * @var $response
   */
  protected $response;

  /**
   * Constructor.
   *
   * @param $response
   */
  public function __construct($response) {
    $this->response = $response;
  }

  /**
   * Implements TMGMTranslatorResponseInterface::getResponse().
   */
  public function getResponse() {
    return $this->response;
  }

  /**
   * Implements TMGMTranslatorResponseInterface::toStatus().
   */
  public function toStatus() {

  }

  /**
   * Implements TMGMTranslatorResponseInterface::toString().
   */
  public function toString() {

  }
}