<?php

namespace Drupal\commerce_xquantity\Form;

use Drupal\commerce\Context;
use Drupal\Core\Form\FormStateInterface;
use Drupal\commerce_cart\Form\AddToCartForm;

/**
 * Overrides the order item add to cart form.
 */
class XquantityAddTocartForm extends AddToCartForm {

  /**
   * The IDs of all forms.
   *
   * @var array
   */
  protected static $formIds = [];

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    if (empty($this->formId)) {
      $this->formId = $this->getBaseFormId();
    }
    $id = $this->formId;

    if (!in_array($id, static::$formIds)) {
      /** @var \Drupal\commerce_order\Entity\OrderItemInterface $order_item */
      $order_item = $this->entity;
      if ($purchased_entity = $order_item->getPurchasedEntity()) {
        $this->formId .= '_' . sha1(serialize($purchased_entity->toArray()));
      }
      else {
        $this->formId .= '_' . sha1(serialize($order_item->toArray()));
      }
    }
    else {
      $base_id = $this->getBaseFormId();
      // For the case when on a page 2+ exactly the same purchased entities.
      while (in_array($id, static::$formIds)) {
        $id = $base_id . '_' . sha1($id . $id);
      }
      $this->formId = $id;
    }
    static::$formIds[] = $this->formId;

    return $this->formId;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    /** @var \Drupal\commerce_order\Entity\OrderItemInterface $order_item */
    $order_item = $this->entity;
    $settings = $order_item->getQuantityWidgetSettings();
    $default = $step = $settings['#step'] ?: $settings['#base_step'];
    $min = $settings['#min'] && $step <= $settings['#min'] ? $settings['#min'] : $step;
    $default = $settings['#default_value'] ?: ($settings['#base_default_value'] ?: $min);
    $value = $form_state->getValue(['quantity', 0, 'value']);
    // If the value is NULL it means the quantity field is disabled.
    $quantity = $value !== NULL ? $value : $default;

    if (!$quantity || ($quantity < $min)) {
      $form_state->setErrorByName('quantity', $this->t('The quantity should be no less than %min', [
        '%min' => $min,
      ]));
      return;
    }
    parent::validateForm($form, $form_state);

    /** @var \Drupal\commerce\PurchasableEntityInterface $purchased_entity */
    $purchased_entity = $order_item->getPurchasedEntity();
    $store = $this->selectStore($purchased_entity);
    $context = new Context(\Drupal::currentUser(), $store);
    $availability = \Drupal::service('commerce.availability_manager');
    $available = $availability->check($purchased_entity, $quantity, $context);
    if (!$available) {
      $form_state->setErrorByName('quantity', $this->t('Unfortunately, the %label is out of stock right at the moment.', [
        '%label' => $purchased_entity->label(),
      ]));
    }
  }

}
