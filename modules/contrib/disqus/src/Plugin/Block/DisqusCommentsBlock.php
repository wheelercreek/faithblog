<?php

/**
 * @file
 * Contains \Drupal\disqus\Plugin\Block\DisqusCommentsBlock.
 */

namespace Drupal\disqus\Plugin\Block;

use Drupal\core\Block\Annotation\Block;
use Drupal\Core\Annotation\Translation;
use Drupal\Core\Form\FormStateInterface;

/**
 *
 * @Block(
 *   id = "disqus_comments",
 *   admin_label = @Translation("Disqus: Comments"),
 *   module = "disqus"
 * )
 */
class DisqusCommentsBlock extends DisqusBaseBlock {
  protected $id = 'disqus_comments';

  /**
   * Overrides DisqusBaseBlock::blockForm().
   */
  public function blockForm($form, FormStateInterface $form_state) {
    $form['disqus'] = array(
      '#type' => 'fieldset',
      '#title' => t('Disqus settings'),
      '#tree' => TRUE,
    );

    $form['disqus']['#description'] = t('This block will be used to display the comments from Disqus. You will first need to configure the disqus comment field for any <a href="!entity-help">entity sub-type </a> (for example, a <a href="!content-type">content type</a>).', array('!entity-help' => \Drupal::url('help.page', array('name' => 'entity')), '!content-type' => \Drupal::url('entity.node_type.collection')));

    return $form;
  }

  /**
   * Overrides DisqusBaseBlock::blockSubmit().
   */
  public function blockSubmit($form, FormStateInterface $form_state) {
  }

  /**
   * {@inheritdoc}
   */
  protected function getRequiredCacheContexts() {
    return array('url');
  }

  /**
   * {@inheritdoc}
   */
  public function build() {
    $disqus_config = \Drupal::config('disqus.settings');
    if ($this->currentUser->hasPermission('view disqus comments')) {
      $keys = $this->routeMatch->getParameters();
      foreach($keys as $key => $value) {
        if(!(is_a($value,'Drupal\Core\Entity\ContentEntityInterface'))) {
          continue;
        }
        // Display if the Disqus field is enabled for the entity.
        $entity = $this->routeMatch->getParameter($key);
        $field = $this->disqusManager->getFields($key);
        if($entity->hasField(key($field))) {
          if ($entity->get(key($field))->status) {
            return array(
              'disqus' => array(
                '#lazy_builder' => ['\Drupal\disqus\Element\Disqus::disqus_element_post_render_cache', [
                  $entity->getEntityTypeId(),
                  $entity->id(),
                ]],
                '#create_placeholder' => TRUE,
                '#cache' => array(
                  'bin' => 'render',
                  'keys' => array('disqus', 'disqus_comments', "{$entity->getEntityTypeId()}", $entity->id()),
                  'tags' => array('content',),
                ),
              ),
            );
          }
        }
      }
    }
  }
}
