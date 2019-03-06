<?php

namespace Drupal\xquantity_stock\Plugin\Action;

use Drupal\Core\Action\ConfigurableActionBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Session\AccountInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Rotate variation stock.
 *
 * @Action(
 *   id = "variation_xquantity_stock_rotate",
 *   label = @Translation("Rotate stock"),
 *   type = "commerce_product_variation"
 * )
 */
class VariationXquantityStockRotate extends ConfigurableActionBase {

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return [];
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $request = \Drupal::request();
    $storage = \Drupal::service('entity_type.manager')->getStorage('commerce_product_variation');
    if ($ids = explode('|', $request->query->get('ids'))) {
      $variations = $storage->loadMultiple($ids);
      $xquantity_stock = [];
      foreach ($variations as $variation) {
        foreach (array_reverse($variation->getFieldDefinitions()) as $definition) {
          if ($definition->getType() == 'xquantity_stock') {
            $xquantity_stock[] = $variation->id();
            continue 2;
          }
        }
      }
      if (!$xquantity_stock) {
        $form['warning'] = [
          '#markup' => new TranslatableMarkup('<h1>To use this functionality you have to have the <span style="color:red">Xquantity Stock</span> field type at least on one of the selected variations.</h1>'),
        ];
      }
      else {
        $form_state->set('variations', $xquantity_stock);
        $form['warning'] = [
          '#markup' => new TranslatableMarkup('<h1><a href=":href" target="_blank">Rotate Stock</a> for <span style="color:red">@count</span> variations</h1>', [
            '@count' => count($variations),
            ':href' => '/admin/help/xquantity_stock#stock-rotation',
          ]),
        ];
        $form['stock'] = [
          '#type' => 'container',
          '#attributes' => ['class' => ['container-inline']],
        ];
        $form['stock']['threshold'] = [
          '#type' => 'number',
          '#step' => '1',
          '#field_suffix' => $this->t('seconds', [], ['context' => 'xquantity stock']),
          '#title' => $this->t('Threshold', [], ['context' => 'xquantity stock']),
          '#default_value' => 1800,
        ];
      }
      $form['cancel'] = [
        '#type' => 'submit',
        '#value' => 'CANCEL AND BACK',
        '#weight' => 1000,
      ];
      // Remove the "Action was applied to N items" message.
      \Drupal::messenger()->deleteByType('status');
    }

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state) {
    if ($form_state->getTriggeringElement()['#id'] != 'edit-cancel') {
      if (!($threshold = $form_state->getValue('threshold')) || !is_numeric($threshold)) {
        \Drupal::messenger()->AddError($this->t('The inserted threshold is not numeric.'));
        return;
      }
      $type_manager = \Drupal::entityTypeManager();
      $storage = $type_manager->getStorage('commerce_order');
      $query = $storage->getQuery();
      $query->accessCheck(FALSE);
      $time = time() - $threshold;
      $query->condition('changed', $time, '<');
      $query->condition('cart', '1', '=');
      $query->condition('locked', '0', '=');
      if ($orders = $query->execute()) {
        $cart_manager = \Drupal::service('commerce_cart.cart_manager');
        $storage = $type_manager->getStorage('commerce_order_item');
        foreach ($form_state->get('variations') as $id) {
          $query = $storage->getQuery();
          $query->accessCheck(FALSE);
          $query->condition('order_id', $orders, 'IN');
          $query->condition('purchased_entity', $id, '=');
          if ($order_items = $query->execute()) {
            foreach ($storage->loadMultiple($order_items) as $order_item) {
              $cart_manager->removeOrderItem($order_item->getOrder(), $order_item);
            }
          }
        }
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function executeMultiple(array $variations) {
    if ($variations) {
      $ids = [];
      foreach ($variations as $variation) {
        $ids[] = $variation->id();
      }
      $url = $variation->toUrl();
      $query = [
        'destination' => \Drupal::request()->getRequestUri(),
        'ids' => implode('|', $ids),
      ];
      $path = $url::fromUserInput('/admin/config/system/actions/configure/' . $this->getPluginId(), ['query' => $query])->toString();
      $response = new RedirectResponse($path);
      $response->send();
    }
  }

  /**
   * {@inheritdoc}
   */
  public function execute($variation = NULL) {
    // Do nothing.
  }

  /**
   * {@inheritdoc}
   */
  public function access($variation, AccountInterface $account = NULL, $return_as_object = FALSE) {
    $result = $variation->access('update', $account, TRUE);

    return $return_as_object ? $result : $result->isAllowed();
  }

}
