<?php
/**
 * @file
 * Contains \Drupal\wcmod\Controller\WcmodController.
 */

namespace Drupal\wcmod\Controller;

use Drupal\Core\Controller\ControllerBase;

class WcmodController extends ControllerBase {
  public function content() {
    return array(
        '#type' => 'markup',
        '#markup' => $this->t('Hello, World!'),
    );
  }
}
?>