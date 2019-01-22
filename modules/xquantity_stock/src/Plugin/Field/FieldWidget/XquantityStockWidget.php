<?php

namespace Drupal\xquantity_stock\Plugin\Field\FieldWidget;

use Drupal\xnumber\Plugin\Field\FieldWidget\XnumberWidget;
use Drupal\xnumber\Utility\Xnumber as Numeric;

/**
 * Plugin implementation of the 'xquantity_stock' widget.
 *
 * @FieldWidget(
 *   id = "xquantity_stock",
 *   label = @Translation("Xquantity Stock"),
 *   field_types = {
 *     "xquantity_stock"
 *   }
 * )
 */
class XquantityStockWidget extends XnumberWidget {

  /**
   * {@inheritdoc}
   */
  public function getFormDisplayModeSettings() {
    $floor = $ceil = $none = t('None', [], ['context' => 'numeric item']);
    // Base field settings.
    $field_settings = $this->getFieldSettings();
    // The current form display mode settings.
    $settings = $this->getSettings();
    if (isset($settings['disable_on_cart']) && $settings['disable_on_cart'] === '') {
      // This is required only by commerce_xquantity module.
      unset($settings['disable_on_cart']);
    }
    // Base or default form display default value.
    $default_value = current(array_column($this->fieldDefinition->getDefaultValueLiteral(), 'value'));
    $default_value = is_numeric($default_value) ? Numeric::toString(($default_value + 0)) : $none;
    $settings['default_value'] = is_numeric($settings['default_value']) ? Numeric::toString(($settings['default_value'] + 0)) : $default_value;

    foreach ($settings as $key => $value) {
      if (!is_numeric($value) && isset($field_settings[$key]) && is_numeric($field_settings[$key])) {
        $settings[$key] = Numeric::toString($field_settings[$key]);
      }
      elseif (is_numeric($value)) {
        $settings[$key] = Numeric::toString($value);
      }
      if ($settings[$key] != '0' && empty($settings[$key])) {
        $settings[$key] = isset($field_settings[$key]) ? Numeric::toString($field_settings[$key]) : $none;
      }
    }

    $min = $settings['min'];
    $max = $settings['max'];
    if (!empty($field_settings['unsigned'])) {
      $floor = '0';
      $settings['min'] = !is_numeric($min) || $min < $floor ? $floor : $min;
    }

    if (isset($field_settings['size'])) {
      $size = $field_settings['size'];
    }
    elseif (isset($field_settings['precision']) && isset($field_settings['scale'])) {
      $size = [
        'precision' => $field_settings['precision'],
        'scale' => $field_settings['scale'],
      ];
    }

    if (isset($size)) {
      $sizes = Numeric::getStorageMaxMin($size);
      if (!empty($field_settings['unsigned'])) {
        $ceil = $sizes['unsigned'];
        $settings['min'] = !is_numeric($min) || $min < $floor ? $floor : $min;
        $settings['max'] = !is_numeric($max) || $max > $ceil ? $ceil : $max;
      }
      else {
        $floor = $sizes['signed']['min'];
        $ceil = $sizes['signed']['max'];
        $settings['min'] = !is_numeric($min) || $min < $floor ? $floor : $min;
        $settings['max'] = !is_numeric($max) || $max > $ceil ? $ceil : $max;
      }
    }

    switch ($this->fieldDefinition->getType()) {
      case 'integer':
      case 'xinteger':
        $step = '1';
        break;

      case 'decimal':
      case 'xdecimal':
      case 'xquantity_stock':
        $step = Numeric::toString(pow(0.1, $field_settings['scale']));
        break;

      case 'float':
      case 'xfloat':
        $step = 'any';
        break;
    }

    $settings['base_default_value'] = $default_value;
    $settings['base_step'] = $step;
    $settings['step'] = $settings['step'] == $none ? $step : $settings['step'];
    $settings['floor'] = $floor;
    $settings['ceil'] = $ceil;

    return $settings;
  }

}
