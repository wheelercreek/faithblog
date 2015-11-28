<?php
namespace Drupal\wcmod\Plugin\Block;
use Drupal\Core\Block\BlockBase;
/**
 * Provides a 'Wcmod' Block
 *
 * @Block(
 *   id = "wcmod_block",
 *   admin_label = @Translation("Wcmod block"),
 * )
 */
class WcmodBlock extends BlockBase {
  /**
   * {@inheritdoc}
   */
  public function build() {
    return array(
      '#markup' => $this->t('Hello, World!'),
    );
  }
}
?>