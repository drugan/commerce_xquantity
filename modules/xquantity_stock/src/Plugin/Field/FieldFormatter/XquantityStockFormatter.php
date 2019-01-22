<?php

namespace Drupal\xquantity_stock\Plugin\Field\FieldFormatter;

use Drupal\Core\Field\Plugin\Field\FieldFormatter\DecimalFormatter;

/**
 * Plugin implementation of the 'xquantity_stock' formatter.
 *
 * @FieldFormatter(
 *   id = "xquantity_stock",
 *   label = @Translation("Xquantity Stock"),
 *   field_types = {
 *     "xquantity_stock"
 *   }
 * )
 */
class XquantityStockFormatter extends DecimalFormatter {
}
