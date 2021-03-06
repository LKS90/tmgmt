<?php
/**
 * @file
 * Contains Drupal\tmgmt\MessageInterface.
 */

namespace Drupal\tmgmt;
use Drupal\Core\Entity\ContentEntityInterface;

/**
 * Interface for the tmgmt_message entity.
 *
 * @ingroup tmgmt_job
 */
interface MessageInterface extends ContentEntityInterface {

  /**
   * Returns the translated message.
   *
   * @return string
   *   The translated message.
   */
  public function getMessage();

  /**
   * Loads the job entity that this job message is attached to.
   *
   * @return \Drupal\tmgmt\JobInterface
   *   The job entity that this job message is attached to or FALSE if there was
   *   a problem.
   */
  public function getJob();

  /**
   * Loads the job entity that this job message is attached to.
   *
   * @return \Drupal\tmgmt\JobItemInterface
   *   The job item entity that this job message is attached to or FALSE if
   *   there was a problem.
   */
  public function getJobItem();

  /**
   * Returns the message type.
   *
   * @return string
   *   Message type.
   */
  public function getType();

}
