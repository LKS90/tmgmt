<?php

/**
 * @file
 * Contains \Drupal\tmgmt_file\Format\FormatInterface.
 */

namespace Drupal\tmgmt_file\Format;

use Drupal\tmgmt\Entity\Job;
use Drupal\tmgmt\JobInterface;

/**
 * Interface for exporting to a given file format.
 */
interface FormatInterface {

  /**
   * Return the file content for the job data.
   *
   * @param $job
   *   The translation job object to be exported.
   *
   * @return
   *   String with the file content.
   */
  function export(JobInterface $job);

  /**
   * Validates that the given file is valid and can be imported.
   *
   * @param $imported_file
   *   File path to the file to be imported.
   *
   * @return Job
   *   Returns the corresponding translation job entity if the import file is
   *   valid, FALSE otherwise.
   */
  function validateImport($imported_file);

  /**
   * Converts an exported file content back to the translated data.
   *
   * @return
   *   Translated data array.
   */
  function import($imported_file);
}
