<?php

/**
 * @file
 * Contains \Drupal\tmgmt_entity\Tests\EntitySuggestionsTest.
 */

namespace Drupal\tmgmt_entity\Tests;

use Drupal\Core\Language\Language;
use Drupal\system\Tests\Entity\EntityUnitTestBase;

/**
 * Basic Source-Suggestions tests.
 */
class EntitySuggestionsTest extends EntityUnitTestBase {

  /**
   * Modules to enable.
   *
   * @var array
   */
  public static $modules = array('tmgmt', 'tmgmt_entity', 'tmgmt_test', 'node', 'entity', 'filter', 'file', 'image', 'language');

  public static function getInfo() {
    return array(
      'name' => 'Entity Suggestions tests',
      'description' => 'Tests suggestion implementation for the entity source plugin',
      'group' => 'Translation Management',
    );
  }

  public function setUp() {
    throw new \Exception('@todo: Update this test.');
    parent::setUp(array('tmgmt_entity'));


    $edit = array(
      'id' => 'de',
    );
    $language = new Language($edit);
    language_save($language);

    // Enable entity translations for nodes and comments.
    $edit = array();
    $edit['entity_translation_entity_types[node]'] = 1;
    $edit['entity_translation_entity_types[file]'] = 1;
    $this->drupalPostForm('admin/config/regional/entity_translation', $edit, t('Save configuration'));
  }

  /**
   * Prepare a node to get suggestions from.
   *
   * Creates a node with two file fields. The first one is not translatable,
   * the second one is. Both fields got two files attached, where one has
   * translatable content (title and atl-text) and the other one not.
   *
   * @return object
   *   The node which is prepared with all needed fields for the suggestions.
   */
  protected function prepareTranslationSuggestions() {
    // Create a content type with fields.
    // Only the first field is a translatable reference.
    $type = $this->drupalCreateContentType();

    $field1 = field_create_field(array(
      'field_name' => 'field1',
      'type' => 'file',
      'cardinality' => -1,
    ));
    $field2 = field_create_field(array(
      'field_name' => 'field2',
      'type' => 'file',
      'cardinality' => -1,
      'translatable' => TRUE,
    ));

    // Create field instances on the content type.
    field_create_instance(array(
      'field_name' => $field1['field_name'],
      'entity_type' => 'node',
      'bundle' => $type->type,
      'label' => 'Field 1',
      'widget' => array('type' => 'file'),
      'settings' => array(),
    ));
    field_create_instance(array(
      'field_name' => $field2['field_name'],
      'entity_type' => 'node',
      'bundle' => $type->type,
      'label' => 'Field 2',
      'widget' => array('type' => 'file'),
      'settings' => array(),
    ));

    // Make the body field translatable from node.
    $info = $this->container->get('field.info')->getField('node', 'body');
    $info['translatable'] = TRUE;
    field_update_field($info);

    // Make the file entity fields translatable.
    $info = $this->container->get('field.info')->getField('node', 'field_file_image_alt_text');
    $info['translatable'] = TRUE;
    field_update_field($info);

    $info = $this->container->get('field.info')->getField('node', 'field_file_image_title_text');
    $info['translatable'] = TRUE;
    field_update_field($info);

    // Create and save files - two with some text and two with no text.
    list($file1, $file2, $file3, $file4) = $this->drupalGetTestFiles('image');
    $file2->field_file_image_alt_text['en'][0] = array(
      'value' => $this->randomName(),
      'type' => 'plain_text',
    );
    $file2->field_file_image_title_text['en'][0] = array(
      'value' => $this->randomName() . ' ' . $this->randomName(),
      'type' => 'plain_text',
    );

    $file4->field_file_image_alt_text['en'][0] = array(
      'value' => $this->randomName(),
      'type' => 'plain_text',
    );
    $file4->field_file_image_title_text['en'][0] = array(
      'value' => $this->randomName() . ' ' . $this->randomName(),
      'type' => 'plain_text',
    );

    file_save($file1);
    file_save($file2);
    file_save($file3);
    file_save($file4);

    // Create a node with two translatable and two non-translatable files.
    $node = $this->drupalCreateNode(array(
      'type' => $type->type,
      'language' => 'en',
      'body' => array('en' => array(
        array(
          'value' => $this->randomName(),
        ),
      )),
      $field1['field_name'] => array(LANGUAGE_NONE => array(
        array(
          'fid' => $file1->fid,
          'display' => 1,
          'description' => '',
        ),
        array(
          'fid' => $file2->fid,
          'display' => 1,
          'description' => '',
        ),
      )),
      $field2['field_name'] => array(LANGUAGE_NONE => array(
        array(
          'fid' => $file3->fid,
          'display' => 1,
          'description' => '',
        ),
        array(
          'fid' => $file4->fid,
          'display' => 1,
          'description' => '',
        ),
      )),
    ));
    return $node;
  }

  /**
   * Test suggested entities from a translation job.
   */
  public function testSuggestions() {
    // Prepare a job and a node for testing.
    $job = $this->createJob();
    $node = $this->prepareTranslationSuggestions();
    $item = $job->addItem('entity', 'node', $node->id());

    // Get all suggestions and clean the list.
    $suggestions = $job->getSuggestions();
    $job->cleanSuggestionsList($suggestions);

    // Check for one suggestion.
    $this->assertEqual(count($suggestions), 2, 'Found two suggestions.');

    // Check for valid attributes on the suggestions.
    $suggestion = array_shift($suggestions);
    $this->assertEqual($suggestion['job_item']->getWordCount(), 3, 'Three translatable words in the suggestion.');
    $this->assertEqual($suggestion['job_item']->plugin, 'entity', 'Got an entity as plugin in the suggestion.');
    $this->assertEqual($suggestion['job_item']->item_type, 'file', 'Got a file in the suggestion.');
    $this->assertEqual($suggestion['job_item']->item_id, $node->field1[LANGUAGE_NONE][1]['fid'], 'File id match between node and suggestion.');
    $this->assertEqual($suggestion['reason'], 'Field Field 1');
    $this->assertEqual($suggestion['from_item'], $item->tjiid);
    $job->addExistingItem($suggestion['job_item']);

    $suggestion = array_shift($suggestions);
    $this->assertEqual($suggestion['job_item']->getWordCount(), 3, 'Three translatable words in the suggestion.');
    $this->assertEqual($suggestion['job_item']->plugin, 'entity', 'Got an entity as plugin in the suggestion.');
    $this->assertEqual($suggestion['job_item']->item_type, 'file', 'Got a file in the suggestion.');
    $this->assertEqual($suggestion['job_item']->item_id, $node->field2[LANGUAGE_NONE][1]['fid'], 'File id match between node and suggestion.');
    $this->assertEqual($suggestion['reason'], 'Field Field 2');
    $this->assertEqual($suggestion['from_item'], $item->tjiid);

    // Add the suggestion to the job and re-get all suggestions.
    $job->addExistingItem($suggestion['job_item']);
    $suggestions = $job->getSuggestions();
    $job->cleanSuggestionsList($suggestions);

    // Check for no more suggestions.
    $this->assertEqual(count($suggestions), 0, 'Found no more suggestion.');
  }

}
