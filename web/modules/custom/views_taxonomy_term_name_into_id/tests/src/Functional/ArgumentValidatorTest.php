<?php

namespace Drupal\Tests\views_taxonomy_term_name_into_id\Functional;

use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\Tests\field\Traits\EntityReferenceTestTrait;
// Evil. The trait we need moved in 8.8.0. Try to use the new location.
use Drupal\Tests\taxonomy\Traits\TaxonomyTestTrait;
// If we're on 8.7.x and lower, the trait won't exist, so we alias the old one.
// @todo Remove this when 8.7.x is EOL.
// @see https://www.drupal.org/node/3092901
if (!trait_exists('\Drupal\Tests\taxonomy\Traits\TaxonomyTestTrait')) {
  class_alias(
    '\Drupal\Tests\taxonomy\Functional\TaxonomyTestTrait',
    '\Drupal\Tests\taxonomy\Traits\TaxonomyTestTrait'
  );
}
use Drupal\Tests\views\Functional\ViewTestBase;
use Drupal\views\Tests\ViewTestData;

/**
 * Tests the taxonomy term name transformed into ID argument validator.
 *
 * @group views_taxonomy_term_name_into_id
 */
class ArgumentValidatorTest extends ViewTestBase {

  use EntityReferenceTestTrait;
  use TaxonomyTestTrait;

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * Views used by this test.
   *
   * @var array
   */
  public static $testViews = ['test_argument_taxonomy_name_into_id'];

  /**
   * The terms used in the tests.
   *
   * @var \Drupal\taxonomy\TermInterface[]
   */
  protected $terms;

  /**
   * The node used in the tests.
   *
   * @var \Drupal\node\NodeInterface
   */
  protected $node;

  /**
   * {@inheritdoc}
   */
  protected function setUp($import_test_views = TRUE, $modules = []): void {
    parent::setUp($import_test_views);

    // Since the access level of the '$modules' property changed from public to
    // protected, it's a pain in the ass for this test to work properly across
    // multiple versions of core with that property defined. Instead of using
    // that to install ourselves, do it manually here.
    $this->container->get('module_installer')->install(['views_taxonomy_term_name_into_id_test']);
    $this->container = \Drupal::getContainer();

    ViewTestData::createTestViews(get_class($this), ['views_taxonomy_term_name_into_id_test']);

    // Create the vocabulary for the tag field.
    $vocabulary = $this->createVocabulary();

    // Create content type.
    $this->drupalCreateContentType([
      'type' => 'article',
    ]);
    $field_name = 'field_' . $vocabulary->id();
    $handler_settings = [
      'target_bundles' => [
        $vocabulary->id() => $vocabulary->id(),
      ],
      'auto_create' => TRUE,
    ];
    $this->createEntityReferenceField('node', 'article', $field_name, 'Tags', 'taxonomy_term', 'default', $handler_settings, FieldStorageDefinitionInterface::CARDINALITY_UNLIMITED);

    // Create terms.
    $this->terms[1] = $this->createTerm($vocabulary, ['name' => 'First']);
    $this->terms[2] = $this->createTerm($vocabulary, ['name' => 'Second']);

    // Create a node and link it to the first term.
    $settings = [
      'type' => 'article',
      'title' => 'Article 1',
    ];
    $settings[$field_name][0]['target_id'] = $this->terms[1]->id();
    $this->node = $this->drupalCreateNode($settings);
  }

  /**
   * Tests view results with taxonomy term name as ID validator.
   */
  public function testViewsWithTaxonomyTermNameArgument() {
    // Test the view with results.
    $this->drupalGet('test_argument_taxonomy_name_into_id/' . $this->terms[1]->getName());
    $this->assertSession()->linkExists($this->node->label());

    // Test the view with no results found.
    $this->drupalGet('test_argument_taxonomy_name_into_id/' . $this->terms[2]->getName());
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->linkNotExists($this->node->label());

    // Test the view with an invalid argument.
    $this->drupalGet('test_argument_taxonomy_name_into_id/xyz');
    $this->assertSession()->statusCodeEquals(404);
  }

}
