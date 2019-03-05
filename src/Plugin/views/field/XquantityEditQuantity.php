<?php

namespace Drupal\commerce_xquantity\Plugin\views\field;

use Drupal\commerce\Context;
use Drupal\Core\Field\FieldFilteredMarkup;
use Drupal\Core\Form\FormStateInterface;
use Drupal\commerce_cart\Plugin\views\field\EditQuantity;
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
      $default_value = $order_item->getQuantity() + 0;
      $settings = $order_item->setQuantityPrices($form_object, $this, $form_state);
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
        '#disabled' => isset($settings['disable_on_cart']) ? $settings['disable_on_cart'] : FALSE,
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
      $qty = $order_item->getQuantity();
      $settings = $order_item->getQuantityWidgetSettings();
      $scale = Numeric::getDecimalDigits($settings['step']);
      if (!empty($quantities[$row_index]) && bccomp($qty, $quantities[$row_index], $scale)) {
        $quantity = $quantities[$row_index];
        $old = (bccomp($qty, $quantity, $scale) === 1);
        if ($available = $purchased_entity) {
          $context = new Context(\Drupal::currentUser(), $order_item->getOrder()->getStore(), time(), [
            'xquantity' => 'cart',
            'old' => $old ? $qty : 0,
          ]);
          $qty = $old ? $quantity : bcsub($quantity, $qty, $scale);
          $available = $availability->check($purchased_entity, $qty, $context);
          if (!$available && $order_item->rotateStock($purchased_entity, $qty, $context)) {
            $available = $availability->check($purchased_entity, $qty, $context);
          }
        }
        if (!$available) {
          $args = [
            '%quantity' => $quantity,
            '%label' => $purchased_entity ? $purchased_entity->label() : $this->t('???'),
            ':href' => $purchased_entity ? $purchased_entity->toUrl()->toString() : '/',
          ];
          $msg = $this->t('Unfortunately, the quantity %quantity of the %label is not available right at the moment.', $args);

          \Drupal::logger('xquantity_stock')->warning($this->t('Possibly the <a href=":href">%label</a> with the quantity %quantity is out of stock.', $args));

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
    $form_object = $form_state->getFormObject();
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
        if ($purchased_entity = $order_item->getPurchasedEntity()) {
          $order_item->setQuantityPrices($form_object, $this, $form_state);
          if ($price = $order_item->getQuantityPrice($form_object, $purchased_entity, $quantity)) {
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
