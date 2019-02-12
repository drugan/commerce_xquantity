<?php

namespace Drupal\commerce_xquantity\Plugin\Field\FieldWidget;

use Drupal\Core\Form\FormStateInterface;
use Drupal\xnumber\Plugin\Field\FieldWidget\XnumberWidget;
use Drupal\Core\Field\FieldItemListInterface;
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
    $order_item = $form_state->getFormObject()->getEntity();
    if ($order_item->getTargetEntityTypeId() != 'commerce_order_item') {
      return $element;
    }
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
      $scale = Numeric::getDecimalDigits($settings['step']);
      $textarea = [
        '#type' => 'textarea',
        '#rows' => 1,
        '#cols' => 2,
        '#attributes' => [
          'style' => 'resize:both',
        ],
      ];
      $entity_manager = \Drupal::entityTypeManager();
      $order_item_variation_types = array_keys($entity_manager->getStorage('commerce_product_variation_type')->loadByProperties([
        'orderItemType' => $order_item->getTargetBundle(),
      ]));
      $order_item_product_types = array_keys($entity_manager->getStorage('commerce_product_type')->loadByProperties([
        'variationType' => $order_item_variation_types,
      ]));
      $stores_hint = implode(PHP_EOL, array_keys($entity_manager->getStorage('commerce_store_type')->loadMultiple()));
      $product_types_hint = implode(PHP_EOL, $order_item_product_types);
      $variation_types_hint = implode(PHP_EOL, $order_item_variation_types);
      $roles_hint = '';
      if (\Drupal::currentUser()->hasPermission('administer users')) {
        $roles_hint = implode(PHP_EOL, array_keys($entity_manager->getStorage('user_role')->loadMultiple()));
      }
      $date_start_hint = date('Y-m-d');
      $date_end_hint = date('Y-m-d', strtotime('+1 day'));
      $time_start_hint = date('H:i');
      $time_end_hint = date('H:i', strtotime('+1 hour'));
      $week_days_hint = implode(PHP_EOL, [
        'Monday',
        'Tuesday',
        'Wednesday',
        'Thursday',
        'Friday',
        'Saturday',
        'Sunday',
      ]);
      // Quantity price adjustment settings table.
      $element['qty_price'] = [
        '#type' => 'table',
        '#header' => [
          $this->t('<a href=":href" target="_blank">Qty start<mark>*</mark></a>', [
            ':href' => '/admin/help/commerce_xquantity#qty-start',
          ]),
          $this->t('<a href=":href" target="_blank">Qty end</a>', [
            ':href' => '/admin/help/commerce_xquantity#qty-end',
          ]),
          $this->t('<a href=":href" target="_blank">Date start</a>', [
            ':href' => '/admin/help/commerce_xquantity#date-start',
          ]),
          $this->t('<a href=":href" target="_blank">Date end</a>', [
            ':href' => '/admin/help/commerce_xquantity#date-end',
          ]),
          $this->t('<a href=":href" target="_blank">Time start</a>', [
            ':href' => '/admin/help/commerce_xquantity#time-start',
          ]),
          $this->t('<a href=":href" target="_blank">Time end</a>', [
            ':href' => '/admin/help/commerce_xquantity#time-end',
          ]),
          $this->t('<a href=":href" target="_blank">Week days</a>', [
            ':href' => '/admin/help/commerce_xquantity#week-days',
          ]),
          $this->t('<a href=":href" target="_blank">Var IDs</a>', [
            ':href' => '/admin/help/commerce_xquantity#var-ids',
          ]),
          $this->t('<a href=":href" target="_blank">Prod IDs</a>', [
            ':href' => '/admin/help/commerce_xquantity#prod-ids',
          ]),
          $this->t('<a href=":href" target="_blank">Var types</a>', [
            ':href' => '/admin/help/commerce_xquantity#var-types',
          ]),
          $this->t('<a href=":href" target="_blank">Prod types</a>', [
            ':href' => '/admin/help/commerce_xquantity#prod-types',
          ]),
          $this->t('<a href=":href" target="_blank">Stores</a>', [
            ':href' => '/admin/help/commerce_xquantity#stores',
          ]),
          $this->t('<a href=":href" target="_blank">Roles</a>', [
            ':href' => '/admin/help/commerce_xquantity#roles',
          ]),
          $this->t('<a href=":href" target="_blank">List price</a>', [
            ':href' => '/admin/help/commerce_xquantity#list-price',
          ]),
          $this->t('<a href=":href" target="_blank">Operation</a>', [
            ':href' => '/admin/help/commerce_xquantity#operation',
          ]),
          $this->t('<a href=":href" target="_blank">Value</a>', [
            ':href' => '/admin/help/commerce_xquantity#value',
          ]),
          $this->t('<a href=":href" target="_blank">Adjust type</a>', [
            ':href' => '/admin/help/commerce_xquantity#adjust-type',
          ]),
          $this->t('<a href=":href" target="_blank">Notify</a>', [
            ':href' => '/admin/help/commerce_xquantity#notify',
          ]),
        ],
        '#input' => FALSE,
      ];
      foreach (range(0, ($qty_prices - 1)) as $i) {
        $qty_start = $qty_end = $date_start = $date_end = $time_start = $time_end = $week_days = $variation_ids = $product_ids = $variation_types = $product_types = $stores = $roles = $list = $adjust_value = '';
        $adjust_op = 'subtract';
        $adjust_type = 'percentage';
        $notify = [];
        if (!empty($settings['qty_price'][$i])) {
          extract($settings['qty_price'][$i]);
        }
        // TODO: Remove this after a while.
        $notify = (array) $notify;
        $quantity_start = empty($qty_start) && empty($quantity_start) ? $settings['min'] : (empty($qty_start) ? bcadd($settings['min'], $quantity_start, $scale) : $qty_start);

        $row = &$element['qty_price'][$i];
        $row['qty_start'] = [
          '#type' => 'number',
          '#min' => $settings['min'],
          '#max' => $settings['max'],
          '#step' => $settings['step'],
          '#attributes' => [
            'style' => 'width:5em',
          ],
          '#required' => TRUE,
          '#default_value' => $qty_start ?: $quantity_start,
        ];
        $row['qty_end'] = [
          '#required' => FALSE,
          '#default_value' => $qty_end,
        ] + $row['qty_start'];
        $row['date_start'] = [
          '#placeholder' => $date_start_hint,
          '#default_value' => $date_start,
        ] + $textarea;
        $row['date_end'] = [
          '#placeholder' => $date_end_hint,
          '#default_value' => $date_end,
        ] + $textarea;
        $row['time_start'] = [
          '#placeholder' => $time_start_hint,
          '#default_value' => $time_start,
        ] + $textarea;
        $row['time_end'] = [
          '#placeholder' => $time_end_hint,
          '#default_value' => $time_end,
        ] + $textarea;
        $row['week_days'] = [
          '#placeholder' => $week_days_hint,
          '#default_value' => $week_days,
        ] + $textarea;
        $row['variation_ids'] = [
          '#default_value' => $variation_ids,
        ] + $textarea;
        $row['product_ids'] = [
          '#default_value' => $product_ids,
        ] + $textarea;
        $row['variation_types'] = [
          '#placeholder' => $variation_types_hint,
          '#default_value' => $variation_types,
        ] + $textarea;
        $row['product_types'] = [
          '#placeholder' => $product_types_hint,
          '#default_value' => $product_types,
        ] + $textarea;
        $row['stores'] = [
          '#placeholder' => $stores_hint,
          '#default_value' => $stores,
        ] + $textarea;
        $row['roles'] = [
          '#placeholder' => $roles_hint,
          '#default_value' => $roles,
        ] + $textarea;
        $row['list'] = [
          '#type' => 'checkbox',
          '#default_value' => $list,
        ];
        $row['adjust_op'] = [
          '#type' => 'select',
          '#options' => [
            'subtract'  => $this->t('Subtract', [], ['context' => 'numeric item']),
            'add' => $this->t('Add'),
          ],
          '#default_value' => $adjust_op,
        ];
        $row['adjust_value'] = [
          '#type' => 'number',
          '#min' => '0.000001',
          '#step' => '0.000001',
          '#attributes' => [
            'style' => 'width:5em',
          ],
          '#default_value' => $adjust_value,
        ];
        $row['adjust_type'] = [
          '#type' => 'select',
          '#options' => [
            'percentage' => $this->t('Percentage', [], ['context' => 'commerce quantity']),
            'fixed_number'  => $this->t('Fixed Number', [], ['context' => 'commerce quantity']),
          ],
          '#default_value' => $adjust_type,
        ];
        $row['notify'] = [
          '#type' => 'checkboxes',
          '#options' => [
            'add_to_cart' => $this->t('form', [], ['context' => 'commerce quantity']),
            'shopping_cart' => $this->t('cart', [], ['context' => 'commerce quantity']),
          ],
          '#default_value' => $notify,
        ];
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
    if ($order_item->getEntityTypeId() == 'commerce_order_item') {
      $order_item->setQuantityPrices($form_object, $this, $form_state);
    }

    return $element;
  }

}
