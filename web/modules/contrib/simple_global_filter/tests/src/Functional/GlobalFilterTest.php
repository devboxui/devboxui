<?php

namespace Drupal\Tests\simple_global_filter\Functional;

use Drupal\block_content\Entity\BlockContent;
use Drupal\block_content\Entity\BlockContentType;
use Drupal\Tests\BrowserTestBase;
use Drupal\Tests\taxonomy\Traits\TaxonomyTestTrait;

/**
 * Test for the global filter.
 * These tests create a taxonomy vocabulary with 2 terms: Finland and Sweden.
 * Then a global filter is created against this vocabulary. A couple of blocks
 * are created and its visibility configured with the global filter.
 * The test finally checks that the visibility of the blocks works, according
 * to the value of the global filter being selected.
 *
 * @group simple_global_filter
 */
class GlobalFilterTest extends BrowserTestBase {

  use TaxonomyTestTrait;

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stable';

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['taxonomy', 'node', 'block', 'block_content', 'simple_global_filter'];

  /**
   * {@inheritdoc}
   *
   * This is set to FALSE here to avoid problems when setting the global filter
   * value.
   */
  protected $strictConfigSchema = FALSE;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    // Creates the page content type if it does not already exists.
    if ($this->profile != 'standard') {
      $this->drupalCreateContentType([
        'type' => 'page',
        'name' => 'Basic page',
        'display_submitted' => FALSE,
      ]);
    }

    // Creates admin user with enough permissions.
    $admin_user = $this->drupalCreateUser([
      'administer site configuration', 'administer blocks', 'create page content',
      'edit own page content',
    ]);
    $this->drupalLogin($admin_user);

    // This block adds the 'Add Global filter' link used later.
    $this->drupalPlaceBlock('local_actions_block');

    // Create a block type 'basic''.
    $bundle = BlockContentType::create([
      'id' => 'basic',
      'label' => 'basic',
      'revision' => FALSE,
    ]);
    $bundle->save();

    // Create nodes for browse later to them and avoid cache hits.
    $node_1 = $this->drupalCreateNode();
    $node_2 = $this->drupalCreateNode();
  }

  /**
   * Test the global filter using the cookie option.
   */
  public function testCookieGlobalFilter() {

    // Create the vocabulary with some terms.
    $vocabulary = $this->createVocabulary();
    $finland_term = $this->createTerm($vocabulary, ['name' => 'Finland']);
    $sweden_term = $this->createTerm($vocabulary, ['name' => 'Sweden']);

    // Go to global filter configuration page.
    $this->drupalGet('admin/structure/global_filter');
    $this->clickLink('Add Global filter');
    $this->assertSession()->titleEquals('Add global filter | Drupal');

    // Fill and submit the form for creating the global filter.
    $edit = [];
    $edit['label'] = 'GlobalFilterLabel';
    $edit['id'] = 'globalfilter';
    $edit['vocabulary_name'] = $vocabulary->id();
    $edit['display_in_url'] = '1';
    $edit['storing_mode'] = 'cookie';
    $edit['display_all_option'] = '1';
    $edit['display_all_label'] = 'All';
    $this->submitForm($edit, 'Save');
    $this->assertSession()->pageTextContains("Created the {$edit['label']} Global filter.");

    // Create 2 basic blocks:
    $finland_block = BlockContent::create([
      'info' => 'Only Finland',
      'type' => 'basic',
    ]);
    $finland_block->save();

    $sweden_block = BlockContent::create([
      'info' => 'Only Sweden',
      'type' => 'basic',
    ]);
    $sweden_block->save();

    // Place the finnish block, visible only when Finland is selected.
    $this->drupalPlaceBlock('block_content:' . $finland_block->uuid(), [
      'label' => $finland_block->get('info')->getString(),
      'visibility' => [
        'global_filter_condition:' . $edit['id'] => [
          'global_filter_' . $edit['id'] => [
            $finland_term->id() => $finland_term->id(),
          ],
        ],
      ],
    ]);

    // Place the swedish block, it will be visible only when Sweden is selected.
    $this->drupalPlaceBlock('block_content:' . $sweden_block->uuid(), [
      'label' => $sweden_block->get('info')->getString(),
      'visibility' => [
        'global_filter_condition:' . $edit['id'] => [
          'global_filter_' . $edit['id'] => [
            $sweden_term->id() => $sweden_term->id(),
          ],
        ],
      ],
    ]);

    // Set the global filter value to Finland.
    \Drupal::service('simple_global_filter.global_filter')->set('globalfilter', $finland_term->id());
    $value = \Drupal::service('simple_global_filter.global_filter')->get('globalfilter');
    $this->assertEquals($finland_term->id(), $value);
    // For testing, the cookie set by the global filter is lost. We recreate the
    // cookie again.
    $this->getSession()->setCookie("Drupal_simple_global_filter_default", json_encode(['globalfilter' => $value]));
    $this->drupalGet('node/1');
    // Finnish block should be displayed, and Swedish block should not be
    // displayed.
    $this->assertSession()->pageTextContains($finland_block->get('info')->getString());
    $this->assertSession()->pageTextNotContains($sweden_block->get('info')->getString());

    // Set the global filter to Sweden and repeat same process.
    \Drupal::service('simple_global_filter.global_filter')->set('globalfilter', $sweden_term->id());
    $value = \Drupal::service('simple_global_filter.global_filter')->get('globalfilter');
    $this->assertEquals($sweden_term->id(), $value);
    $this->getSession()->setCookie("Drupal_simple_global_filter_default", json_encode(['globalfilter' => $value]));
    // Go to another node, to ensure that we don't get cached result.
    $this->drupalGet('node/2');
    // Swedish block should be displayed, and Finnish block should not be
    // displayed.
    $this->assertSession()->pageTextContains($sweden_block->get('info')->getString());
    $this->assertSession()->pageTextNotContains($finland_block->get('info')->getString());
  }

}
