<?php

namespace Drupal\xquantity_stock\Plugin\Action;

use Drupal\Core\Action\ConfigurableActionBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Session\AccountInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\xnumber\Utility\Xnumber as Numeric;

/**
 * Adjust variation stock.
 *
 * @Action(
 *   id = "variation_xquantity_stock_adjust",
 *   label = @Translation("Adjust stock"),
 *   type = "commerce_product_variation"
 * )
 */
class VariationXquantityStockAdjust extends ConfigurableActionBase {

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
          '#markup' => new TranslatableMarkup('<h1>Adjust Stock Quantity for <span style="color:red">@count</span> variations</h1><mark>Note if the result of adjusting is a negative stock it will be converted to a <span style="color:red">0</span> quantity.</mark>', ['@count' => count($variations)]),
        ];
        $form['stock'] = [
          '#type' => 'container',
          '#attributes' => ['class' => ['container-inline']],
        ];
        $form['stock']['adjust_op'] = [
          '#type' => 'select',
          '#options' => [
            'add' => $this->t('Add'),
            'subtract'  => $this->t('Subtract'),
          ],
        ];
        $form['stock']['adjust_value'] = [
          '#type' => 'number',
          '#step' => !empty($settings['step']) ? $settings['step'] : pow(0.1, $settings['scale']),
          '#min' => !empty($settings['min']) ? $settings['min'] : ($settings['unsigned'] ? '0' : ''),
        ];
        if (!empty($settings['default_value'])) {
          $form['stock']['adjust_value']['#default_value'] = $settings['default_value'];
        }
        $form['stock']['adjust_type'] = [
          '#type' => 'select',
          '#options' => [
            'fixed_number'  => $this->t('Fixed Number'),
            'percentage' => $this->t('Percentage'),
          ],
        ];
        $scale = Numeric::getDecimalDigits($form['stock']['adjust_value']['#step']);
        $form_state->set('xquantity_stock_scale', $scale);
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
      if (empty($xquantity_stock) || !is_numeric($value = $values['adjust_value'])) {
        \Drupal::messenger()->AddError($this->t('The inserted adjust value is not numeric.'));
        return;
      }
      $op = $values['adjust_op'] == 'add' ? 'bcadd' : 'bcsub';
      $scale = $form_state->get('xquantity_stock_scale');
      foreach ($form_state->get('variations') as $variation) {
        $stock = $variation->get($xquantity_stock)->value;
        if ($values['adjust_type'] == 'fixed_number') {
          $quantity = $op($stock, $value, $scale);
        }
        else {
          $quantity = $op($stock, bcmul(bcdiv($stock, '100'), $value), $scale);
        }
        $quantity = (bccomp($quantity, '0', $scale) === 1) ? $quantity : '0';
        $variation->set($xquantity_stock, $quantity)->save();
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
