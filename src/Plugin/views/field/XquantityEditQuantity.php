<?php

namespace Drupal\commerce_xquantity\Plugin\views\field;

use Drupal\commerce\Context;
use Drupal\Core\Render\Markup;
use Drupal\Core\Form\FormStateInterface;
use Drupal\commerce_cart\Plugin\views\field\EditQuantity;

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
    // Make sure we do not accidentally cache this form.
    $form['#cache']['max-age'] = 0;
    // The view is empty, abort.
    if (empty($this->view->result)) {
      unset($form['actions']);
      return;
    }

    $form[$this->options['id']]['#tree'] = TRUE;
    foreach ($this->view->result as $row_index => $row) {
      /** @var \Drupal\commerce_order\Entity\OrderItemInterface $order_item */
      $order_item = $this->getEntity($row);
      $attr = $order_item->getQuantityWidgetSettings();

      $form[$this->options['id']][$row_index] = [
        '#type' => 'number',
        '#title' => $this->t('Quantity'),
        '#title_display' => 'invisible',
        '#default_value' => $order_item->getQuantity() + 0,
        '#size' => 4,
        '#min' => isset($attr['#min']) && is_numeric($attr['#min']) ? $attr['#min'] : '1',
        '#max' => isset($attr['#max']) && is_numeric($attr['#max']) ? $attr['#max'] : '9999',
        '#step' => isset($attr['#step']) && is_numeric($attr['#step']) ? $attr['#step'] : '1',
        '#placeholder' => empty($attr['#placeholder']) ? '' : $attr['#placeholder'],
        '#field_prefix' => empty($attr['#prefix']) ? '' : Markup::create($attr['#prefix']),
        '#field_suffix' => empty($attr['#suffix']) ? '' : Markup::create($attr['#suffix']),
        // Do not allow to change the default quantity if the quantity widget
        // is hidden on the 'Add to cart' form display.
        '#disabled' => $attr['add_to_cart_quantity_hidden'],
      ];
    }
    // Replace the form submit button label.
    $form['actions']['submit']['#value'] = $this->t('Update cart');
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
      if (isset($quantities[$row_index]) && $order_item->getQuantity() != $quantities[$row_index]) {
        $context = new Context(\Drupal::currentUser(), $order_item->getOrder()->getStore());
        $available = $availability->check($purchased_entity, $quantities[$row_index], $context);
        if (!$available) {
          $form_state->setError($form[$this->options['id']][$row_index], $this->t('Unfortunately, the %label is out of stock right at the moment.', [
            '%label' => $purchased_entity->label(),
          ]));
        }
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function viewsFormSubmit(array &$form, FormStateInterface $form_state) {
    $quantities = $form_state->getValue($this->options['id'], []);

    foreach ($this->view->result as $row_index => $row) {
      /** @var \Drupal\commerce_order\Entity\OrderItemInterface $order_item */
      $order_item = $this->getEntity($row);
      if ($quantity = isset($quantities[$row_index])) {
        $quantity = $quantities[$row_index];
      }
      // Remove order item if quantity has no any positive value.
      if (empty($quantity) || $quantity < 0) {
        $this->cartManager->removeOrderItem($order_item->getOrder(), $order_item);
      }
      elseif ($order_item->getQuantity() != $quantity) {
        $order_item->setQuantity($quantities[$row_index]);
        // Otherwise update quantity of order item.
        $order = $order_item->getOrder();
        $this->cartManager->updateOrderItem($order, $order_item, FALSE);
        // Tells commerce_cart_order_item_views_form_submit() to save the order.
        $form_state->set('quantity_updated', TRUE);
      }
    }
  }

}
