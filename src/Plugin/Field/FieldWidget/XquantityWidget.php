<?php

namespace Drupal\commerce_xquantity\Plugin\Field\FieldWidget;

use Drupal\Core\Form\FormStateInterface;
use Drupal\xnumber\Plugin\Field\FieldWidget\XnumberWidget;

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
    ] + parent::defaultSettings();
  }

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, FormStateInterface $form_state) {
    $element = parent::settingsForm($form, $form_state);
    if ($form_state->getFormObject()->getEntity()->getTargetEntityTypeId() == 'commerce_order_item') {
      $element['disable_on_cart'] = [
        '#type' => 'checkbox',
        '#title' => $this->t('Disable on cart', [], ['context' => 'numeric item']),
        '#default_value' => $this->getSetting('disable_on_cart'),
        '#description' => t('Whether to disable quantity field on a Shopping Cart for a given order item (customer sees the quantity but unable to change it).'),
      ];
    }

    return $element;
  }

}
