<?php

/**
 * @file
 * Contains \Drupal\disqus\Plugin\Block\DisqusPopularThreadsBlock.
 */

namespace Drupal\disqus\Plugin\Block;

use Drupal\core\Block\Annotation\Block;
use Drupal\Core\Annotation\Translation;

/**
 *
 * @Block(
 *   id = "disqus_popular_threads",
 *   admin_label = @Translation("Disqus: Popular Threads"),
 *   module = "disqus"
 * )
 */
class DisqusPopularThreadsBlock extends DisqusBaseBlock {
  protected $id = 'disqus_popular_threads';

  /**
   * {@inheritdoc}
   */
  public function build() {
    return array(
      '#title' => t('Popular Threads'),
      $this->render('popular_threads_widget')
    );
  }
}
