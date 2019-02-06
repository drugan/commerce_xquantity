<?php

namespace Drupal\commerce_xquantity\Plugin\Field\FieldWidget;

use Drupal\Core\Form\FormStateInterface;
use Drupal\xnumber\Plugin\Field\FieldWidget\XnumberWidget;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\commerce_price\Calculator;
use Drupal\xnumber\Utility\Xnumber as Numeric;

/**
 * Overrides the 'xnumber' widget.
 */
class XquantityWidget extends XnumberWidget {

  /**
   * {@inheritdoc}
   */
  public static function defaultSettings() {
    return [
      'disable_on_cart' => '',
      'qty_prices' => '0',
      'qty_price' => [[]],
    ] + parent::defaultSettings();
  }

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, FormStateInterface $form_state) {
    $element = parent::settingsForm($form, $form_state);
    if ($form_state->getFormObject()->getEntity()->getTargetEntityTypeId() == 'commerce_order_item') {
      $settings = $this->getFormDisplayModeSettings();
      $element['disable_on_cart'] = [
        '#type' => 'checkbox',
        '#title' => $this->t('Disable on cart', [], ['context' => 'commerce quantity']),
        '#default_value' => isset($settings['disable_on_cart']) ? $settings['disable_on_cart'] : $this->getSetting('disable_on_cart'),
        '#description' => t('Whether to disable quantity field on a Shopping Cart for a given order item (customer sees the quantity but unable to change it).'),
      ];
      $element['qty_prices'] = [
        '#type' => 'number',
        '#min' => '0',
        '#step' => '1',
        '#default_value' => $settings['qty_prices'],
        '#title' => $this->t('Quantity price adjustments', [], ['context' => 'commerce quantity']),
        '#description' => $this->t('Read more: <a href=":href" target="_blank">admin/help/commerce_xquantity#quantity-price-adjustments</a>', [
          ':href' => '/admin/help/commerce_xquantity#quantity-price-adjustments',
        ]),
      ];
      $element['qty_price'] = [[]];
      if ($qty_prices = $element['qty_prices']['#default_value']) {
        foreach (range(0, ($qty_prices - 1)) as $i) {
          $start = $end = $adjust_value = $notify = '';
          $adjust_op = 'subtract';
          $adjust_type = 'percentage';
          if (!empty($settings['qty_price'][$i])) {
            extract($settings['qty_price'][$i]);
          }
          $element['qty_price'][$i] = [
            '#type' => 'container',
          ];
          $element['qty_price'][$i] = [
            '#type' => 'container',
            '#attributes' => ['class' => ['container-inline']],
          ];
          $element['qty_price'][$i]['start'] = [
            '#type' => 'number',
            '#default_value' => $start,
            '#required' => TRUE,
            '#min' => $settings['min'],
            '#max' => $settings['max'],
            '#step' => $settings['step'],
            '#prefix' => $this->t('Qty range start', [], ['context' => 'commerce quantity']),
            '#suffix' => 'end',
          ];
          $element['qty_price'][$i]['end'] = [
            '#type' => 'number',
            '#default_value' => $end,
            '#min' => $settings['min'],
            '#max' => $settings['max'],
            '#step' => $settings['step'],
          ];
          $element['qty_price'][$i]['adjust_op'] = [
            '#type' => 'select',
            '#prefix' => $this->t('Price', [], ['context' => 'commerce quantity']),
            '#options' => [
              'subtract'  => $this->t('Subtract', [], ['context' => 'numeric item']),
              'add' => $this->t('Add'),
            ],
            '#default_value' => $adjust_op,
          ];
          $element['qty_price'][$i]['adjust_value'] = [
            '#type' => 'number',
            '#min' => '0.000001',
            '#step' => '0.000001',
            '#default_value' => $adjust_value,
            '#required' => TRUE,
          ];
          $element['qty_price'][$i]['adjust_type'] = [
            '#type' => 'select',
            '#options' => [
              'percentage' => $this->t('Percentage', [], ['context' => 'commerce quantity']),
              'fixed_number'  => $this->t('Fixed Number', [], ['context' => 'commerce quantity']),
            ],
            '#default_value' => $adjust_type,
          ];
          $element['qty_price'][$i]['notify'] = [
            '#type' => 'checkbox',
            '#suffix' => $this->t('Notify', [], ['context' => 'commerce quantity']),
            '#default_value' => $notify,
          ];
        }
      }
    }

    return $element;
  }

  /**
   * {@inheritdoc}
   */
  public function settingsSummary() {
    $summary = [];
    $settings = $this->getFormDisplayModeSettings();
    unset($settings['qty_price']);

    foreach ($settings as $name => $value) {
      $summary[] = "{$name}: {$value}";
    }

    return $summary;
  }

  /**
   * {@inheritdoc}
   */
  public function formElement(FieldItemListInterface $items, $delta, array $element, array &$form, FormStateInterface $form_state) {
    $element = parent::formElement($items, $delta, $element, $form, $form_state);
    $form_object = $form_state->getFormObject();
    $order_item = $form_object->getEntity();
    if ($order_item->getEntityTypeId() != 'commerce_order_item') {
      return $element;
    }
    $settings = $this->getSettings();
    if ($settings['qty_prices'] && ($count = count($settings['qty_price']))) {
      $price = $order_item->getUnitPrice();
      $formatter = \Drupal::service('commerce_price.currency_formatter');
      $list = '';
      $form_object->quantityScale = Numeric::getDecimalDigits($settings['step']);
      $form_object->quantityPrices = [];
      foreach ($settings['qty_price'] as $index => $qty_price) {
        extract($qty_price);
        if ($start && $adjust_value && ($settings['qty_prices'] > $index)) {
          if ($adjust_type == 'fixed_number') {
            $adjust_price = new $price($adjust_value, $price->getCurrencyCode());
          }
          else {
            $adjust_price = $price->divide('100')->multiply($adjust_value);
          }
          $new = $price->$adjust_op($adjust_price);
          if ($new->isNegative()) {
            $new = $new->multiply('0');
          }
          $form_object->quantityPrices[$start] = [
            'price' => $new,
            'end' => $end,
          ];
          $new = $new->toArray();
          if ($notify) {
            $li = $this->t('Bye <span style="color:yellow;font-weight: bolder;">%qty</span> or more and get <span style="color:yellow;font-weight: bolder;">%price</span> price for an item', [
              '%qty' => $start,
              '%price' => $formatter->format(Calculator::round($new['number'], 2), $new['currency_code']),
            ]);
            $list .= "<li>{$li}</li>";
          }
        }
      }
      $module_handler = \Drupal::moduleHandler();
      $module_handler->alter("xquantity_add_to_cart_qty_prices", $form_object, $this, $form_state);
      $form_state->setFormObject($form_object);
      if ($list) {
        $msg = new TranslatableMarkup("Price adjustments for the %label:<br><ul>{$list}</ul><hr>", [
          '%label' => $order_item->label(),
        ]);
        $module_handler->alter("xquantity_add_to_cart_qty_prices_msg", $msg, $this, $form_state);
        $msg && $this->messenger()->addMessage($msg);
      }
    }

    return $element;
  }

}
