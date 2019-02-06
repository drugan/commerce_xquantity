<?php

namespace Drupal\commerce_xquantity\Plugin\views\field;

use Drupal\commerce\Context;
use Drupal\Core\Field\FieldFilteredMarkup;
use Drupal\Core\Form\FormStateInterface;
use Drupal\commerce_cart\Plugin\views\field\EditQuantity;
use Drupal\commerce_price\Calculator;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\xnumber\Utility\Xnumber as Numeric;

/**
 * Overrides a form element for editing the order item quantity.
 *
 * @ViewsField("commerce_order_item_edit_quantity")
 */
class XquantityEditQuantity extends EditQuantity {

  /**
   * {@inheritdoc}
   */
  public function viewsForm(array &$form, FormStateInterface $form_state) {
    parent::viewsForm($form, $form_state);
    $form_object = $form_state->getFormObject();

    foreach ($this->view->result as $row_index => $row) {
      /** @var \Drupal\commerce_order\Entity\OrderItemInterface $order_item */
      $order_item = $this->getEntity($row);
      $settings = [];
      // Let's remove leading hashes.
      foreach ($order_item->getQuantityWidgetSettings() as $key => $value) {
        $settings[ltrim($key, '#')] = $value;
      }
      $default_value = $order_item->getQuantity() + 0;
      if ($settings['qty_prices'] && ($count = count($settings['qty_price']))) {
        $price = $order_item->getPurchasedEntity()->getPrice();
        $formatter = \Drupal::service('commerce_price.currency_formatter');
        $list = '';
        $form_object->quantityScale = Numeric::getDecimalDigits($settings['step']);
        $form_object->quantityPrices = [];
        // Roll back to initial price.
        $form_object->quantityPrices[$settings['step']] = [
          'price' => $price,
          'end' => '',
        ];
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
      if (is_string($settings['prefix']) && !empty($settings['prefix'])) {
        $prefixes = explode('|', $settings['prefix']);
        $prefix = (count($prefixes) > 1) ? $this->formatPlural($default_value, $prefixes[0], $prefixes[1]) : $prefixes[0];
        $settings['prefix'] = FieldFilteredMarkup::create($prefix);
      }
      if (is_string($settings['suffix']) && !empty($settings['suffix'])) {
        $suffixes = explode('|', $settings['suffix']);
        $suffix = (count($suffixes) > 1) ? $this->formatPlural($default_value, $suffixes[0], $suffixes[1]) : $suffixes[0];
        $settings['suffix'] = FieldFilteredMarkup::create($suffix);
      }

      $form[$this->options['id']][$row_index] = [
        '#type' => 'number',
        '#title' => $this->t('Quantity'),
        '#title_display' => 'invisible',
        '#default_value' => $default_value,
        '#size' => 4,
        '#min' => isset($settings['min']) && is_numeric($settings['min']) ? $settings['min'] : '1',
        '#max' => isset($settings['max']) && is_numeric($settings['max']) ? $settings['max'] : '9999',
        '#step' => isset($settings['step']) && is_numeric($settings['step']) ? $settings['step'] : '1',
        '#placeholder' => empty($settings['placeholder']) ? '' : $settings['placeholder'],
        '#field_prefix' => $settings['prefix'],
        '#field_suffix' => $settings['suffix'],
        // Might be disabled on the quantity field form display widget.
        '#disabled' => $settings['disable_on_cart'],
      ];
    }
  }

  /**
   * Validate the views form input.
   *
   * @param array $form
   *   An associative array containing the structure of the form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   *
   * @see https://www.drupal.org/project/commerce/issues/2903504#comment-12228721
   * @see https://www.drupal.org/project/commerce/issues/2903504#comment-12378700
   */
  public function viewsFormValidate(array &$form, FormStateInterface $form_state) {
    $quantities = $form_state->getValue($this->options['id'], []);
    $availability = \Drupal::service('commerce.availability_manager');
    foreach ($this->view->result as $row_index => $row) {
      /** @var \Drupal\commerce_order\Entity\OrderItemInterface $order_item */
      $order_item = $this->getEntity($row);
      /** @var \Drupal\commerce\PurchasableEntityInterface $purchased_entity */
      $purchased_entity = $order_item->getPurchasedEntity();
      $quantity = $order_item->getQuantity();
      if (!empty($quantities[$row_index]) && ($quantity != $quantities[$row_index])) {
        $context = new Context(\Drupal::currentUser(), $order_item->getOrder()->getStore(), time(), ['xquantity' => 'cart', 'old' => $quantity]);
        $available = $purchased_entity && $availability->check($purchased_entity, $quantities[$row_index], $context);
        if (!$available) {
          $msg = $this->t('Unfortunately, the quantity %quantity of the %label is not available right at the moment.', [
            '%quantity' => $quantity,
            '%label' => $purchased_entity ? $purchased_entity->label() : $this->t('???'),
          ]);
          $purchased_entity && $this->moduleHandler->alter("xquantity_add_to_cart_not_available_msg", $msg, $quantity, $purchased_entity);
          $form_state->setError($form[$this->options['id']][$row_index], $msg);
        }
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function viewsFormSubmit(array &$form, FormStateInterface $form_state) {
    $triggering_element = $form_state->getTriggeringElement();
    if (empty($triggering_element['#update_cart'])) {
      // Don't run when the "Remove" or "Empty cart" buttons are pressed.
      return;
    }

    $order_storage = $this->entityTypeManager->getStorage('commerce_order');
    /** @var \Drupal\commerce_order\Entity\OrderInterface $cart */
    $cart = $order_storage->load($this->view->argument['order_id']->getValue());
    $quantities = $form_state->getValue($this->options['id'], []);
    $save_cart = FALSE;
    foreach ($quantities as $row_index => $quantity) {
      if (!is_numeric($quantity) || $quantity < 0) {
        // The input might be invalid if the #required or #min attributes
        // were removed by an alter hook.
        continue;
      }
      /** @var \Drupal\commerce_order\Entity\OrderItemInterface $order_item */
      $order_item = $this->getEntity($this->view->result[$row_index]);
      if ($order_item->getQuantity() == $quantity) {
        // The quantity hasn't changed.
        continue;
      }

      if ($quantity > 0) {
        $price = NULL;
        $form_object = $form_state->getFormObject();
        $scale = $form_object->quantityScale ?: 0;
        if ($qty_prices = $form_object->quantityPrices) {
          foreach ($qty_prices as $qty => $adjustment) {
            $start = bccomp($qty, $quantity, $scale);
            $end = $adjustment['end'] ? bccomp($quantity, $adjustment['end'], $scale) : 0;
            if (($end === 1) || ($start === 1)) {
              continue;
            }
            $price = $adjustment['price'];
          }
          if ($price) {
            if (!$price->equals($order_item->getUnitPrice())) {
              $order_item->setUnitPrice($price, TRUE);
            }
          }
        }
        $order_item->setQuantity($quantity);
        $this->cartManager->updateOrderItem($cart, $order_item, FALSE);
      }
      else {
        // Treat quantity "0" as a request for deletion.
        $this->cartManager->removeOrderItem($cart, $order_item, FALSE);
      }
      $save_cart = TRUE;
    }

    if ($save_cart) {
      $cart->save();
      if (!empty($triggering_element['#show_update_message'])) {
        $this->messenger->addMessage($this->t('Your shopping cart has been updated.'));
      }
    }
  }

}
