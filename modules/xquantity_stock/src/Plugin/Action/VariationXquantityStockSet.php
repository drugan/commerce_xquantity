<?php

namespace Drupal\xquantity_stock\Plugin\Action;

use Drupal\Core\Action\ConfigurableActionBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Session\AccountInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Set variation stock.
 *
 * @Action(
 *   id = "variation_xquantity_stock_set",
 *   label = @Translation("Set stock"),
 *   type = "commerce_product_variation"
 * )
 */
class VariationXquantityStockSet extends ConfigurableActionBase {

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
      $variation = reset($variations);
      $xquantity_stock = FALSE;
      foreach (array_reverse($variation->getFieldDefinitions()) as $definition) {
        if ($definition->getType() == 'xquantity_stock') {
          $xquantity_stock = TRUE;
          $form_state->set('xquantity_stock', $definition->getName());
          $settings = [];
          $type_id = $variation->getOrderItemTypeId();
          $form_display = entity_get_form_display('commerce_order_item', $type_id, 'add_to_cart');
          $quantity = $form_display->getComponent('quantity');
          if (!$quantity) {
            $form_display = entity_get_form_display('commerce_order_item', $type_id, 'default');
            $quantity = $form_display->getComponent('quantity');
          }
          if (isset($quantity['settings']['step'])) {
            $settings = $form_display->getRenderer('quantity')->getFormDisplayModeSettings();
          }
          $settings += $definition->getFieldStorageDefinition()->getSettings();
          break;
        }
      }
      if (!$xquantity_stock) {
        $form['warning'] = [
          '#markup' => new TranslatableMarkup('<h1>To use this functionality you have to add the <span style="color:red">Xquantity Stock</span> field type to the current variation type.</h1>'),
        ];
      }
      else {
        $form_state->set('variations', array_values($variations));
        $form['warning'] = [
          '#markup' => new TranslatableMarkup('<h1>Set Stock Quantity for <span style="color:red">@count</span> variations</h1>', ['@count' => count($variations)]),
        ];
        $form['stock'] = [
          '#type' => 'container',
          '#attributes' => ['class' => ['container-inline']],
        ];
        $min = $settings['min'];
        $form['stock']['set_value'] = [
          '#type' => 'number',
          '#step' => !empty($settings['step']) ? $settings['step'] : pow(0.1, $settings['scale']),
          '#min' => (!is_numeric($min) || ($min < 0)) && $settings['unsigned'] ? '0' : $min,
        ];
        if (!empty($settings['default_value'])) {
          $form['stock']['set_value']['#default_value'] = $settings['default_value'];
        }
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
      $values = $form_state->getValues();
      $xquantity_stock = $form_state->get('xquantity_stock');
      if (empty($xquantity_stock) || !is_numeric($value = $values['set_value'])) {
        \Drupal::messenger()->AddError($this->t('The inserted value is not numeric.'));
        return;
      }
      foreach ($form_state->get('variations') as $variation) {
        $variation->set($xquantity_stock, $value)->save();
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
