Commerce Extended Quantity
==========================

Allows to set quantity field's **default_value**, **step**, **min**, **max**,
**prefix** and **suffix** on a form display widget. Additionally, validates user
input on the field and order item's quantity availability both on
an *Add to cart* form and quantity update field in a *Shopping cart*. Also,
allows to disable quantity field for a given order item type on
the [cart#](#0 "Shopping cart page"). More info could be found on
the [Extended Number Field ↗](https://github.com/drugan/xnumber) module's page,
on top of which the current module is built.

- [admin/help/commerce_xquantity#set-up](#set-up "Set up")
- [admin/help/commerce_xquantity#disable-on-cart](#disable-on-cart "Disable on cart")
- [admin/help/commerce_xquantity#important-notes](#important-notes "Important notes")
- [admin/help/commerce_xquantity#settings-workflow](#important-notes "Settings workflow")
- [admin/help/commerce_xquantity#empty-string-or-0-quantity](#empty-string-or-0-quantity "Empty string or 0 quantity")
- [admin/help/commerce_xquantity#quantity-vs-items-quantity](#quantity-vs-items-quantity "Quantity vs items quantity")
- [admin/help/commerce_xquantity#module-author](#module-author "Module author")
- [Commerce Extended Quantity on drupal.org ↗](https://www.drupal.org/project/commerce_xquantity)
- [Commerce Extended Quantity on github.com ↗](https://github.com/drugan/commerce_xquantity)

## Set up

If you want to expose *quantity* field for a customer on the *Add to cart* form,
then go to
the [admin/commerce/config/order-item-types/default/edit/form-display/add_to_cart](#0 "Default order item") page
and enable *Quantity* field on the respective form display mode. The default
order item type is taken as an example. Actually, might be any order item.

![Quantity settings summary](images/add-to-cart-mode-summary.png
"Quantity settings summary")

The quantity widget settings' summary explained:

- **default value:** The quantity to pre-fill on an *Add to cart* form by
default.
- **step:** The main setting of the field. Defines the allowed amount to
increment or decrement the field value with. So, the value entered must be an
exact multiple of the amount. The setting also defines a sub-type of the field.
For example, If you set it to an integer value then the field  becomes integer
despite being initially decimal field with precision *14,4*. No decimal values
will be accepted for the field. The same with decimal *step*. If you set it, for
example to *0.5*, then no decimal values like *0.05* or *0.005* or *0.0005* will
be accepted despite the initial decimal part of the field can be up to 4 digits.
However, the *1* or *2* or *99* are valid input values because these numbers are
multiples of the *0.5* step. For the most common set up you might set it to *1*.
If you sell, for example, t-shirts but want your customers add to cart only even
quantities, then you might set it to *2* and force them to
buy *2* or *4* or *98* but not *1* or *3* or *99* t-shirts.
- **min:** The minimal value allowed. Use it to force customers for buying no
less than the value. Should be the multiple of the *step* and no greater than
the *max* property.
- **max:** The maximum value allowed. Should be the multiple of
the *step* and no less than the *min* property. Use it to restrict customers on
the order item quantity to buy.
- **prefix:** The text appearing before the quantity field.
- **suffix:** The text appearing after the quantity field.
- **placeholder:** The text appearing inside the empty quantity field.
- **disable_on_cart:** Whether to disable field in a Shopping cart for the given
order item type.
- **base_default_value:** The value defined for the field in code (*1*).
- **base_step:** The calculated value which is the minimal step for the
decimal field with the precision *14,4* (*0.0001*).
- **floor:** The most possible minimal positive value for the field (*0*). Note
that this value is overriden by the *step* property, which must be greater
than *0* by its nature.
- **ceil:** The most possible maximum value for the decimal field with the
precision *14,4* (*9999999999.9999*).

To change the settings displayed on the widget summary click on the gear icon at
the right of the summary. If you don't want to expose *quantity* field for a
customer on the *Add to cart* form but still require non-default settings for
the field then do the same settings on the *Default* form mode's form display
widget.

![Quantity settings](images/add-to-cart-mode-settings.png
"Quantity settings")

## Disable on cart

There might be cases when a customer should be disallowed to update quantity in
a *Shopping cart*. For example, you did not exposed the field on
an *Add to cart* form supposing the default quantity to be added or, the field
is exposed but you disallow the customer to change their mind on the cart page.
So, they have just two options: either to continue with checkout or remove order
item from a cart. Arguably, this workflow may increase sales rate.

![Disable on cart](images/disable-on-cart.png "Disable on cart")
![Disabled edit quantity](images/disabled-edit-qty.png "Disabled edit quantity")

## Important notes

###### Settings workflow
> When saving the *Xnumber field* widget's settings it is recommended to do it
with the multi-step workflow. First, set and save the *Step* property of the
quantity field which is the definitive setting for all the rest numeric
properties. Then, using the controls at the right of the *Minimum* property
field set the desirable value (optional). Save it. After that, you may set
the *Maximum* property (optional). And finally, set the *Default value* which
will be pre-filled for a customer on an *Add to cart* form or quantity update
field in a *Shopping cart* table (optional). The same with changing the earlier
saved *Step* property. First, set blank for
the *Default value*, *Minimum* and *Maximum* properties and then repeat the
above procces. Later, when you grasp how the module works you can set it up in
one go without getting error messages on an attempt to save a wrong value.


###### Empty string or 0 quantity
> As the quantity field is not required a customer may empty the field and try
to submit the form. On the *Add to cart* form an error will be emitted with the
minimal value allowed to submit to a customer. On the *Shopping cart* table it
just removes the order item from the cart. The same approach is taken with an
attempt to submit a *0* quantity (if the *min* property is not set).

## Quantity vs items quantity

The *Commerce Extended Quantity* module introduces a new notion of the order
item quantity:

`QUANTITY = NUMBER_OF_ITEMS ^ NUMBER_OF_UNITS`

Where **ITEM** is any separate item that must be measured in integer value
== *1*. Where **UNIT** is any non-zero unit of the **ITEM** that must be
measured in decimal up to *4* digits after decimal sign and where one and only
one **ITEM** is a set of multiple **UNITS**.

Another words, if you have *1.999 g* of pizza in your *Shopping cart* then it is
considered as *1* item with the quantity of *1.999*. If you have *2.000 g* of
the pizza then it is the same: *1* item with the quantity of *2.000*. But if
the *step* on the quantity field is integer and the quantity in
the *Shopping cart* is *2*, then it is considered as two separate items with the
quantity of *2*.

The *Drupal Commerce* experts should not be scared by the approach above because
the only place where it is implemented is the *Shopping cart block*, where
quantities displayed differently for the fields having integer or
decimal *step*.

@PHPFILE: modules/contrib/commerce_xquantity/src/Plugin/Block/XquantityCartBlock.php LINE:46 PADD:5 :PHPFILE@

All the rest code such as price or tax calculation has no any impact with the
notion and as usual uses this method:

```
$quantity = $order_item->getQuantity();
```

The current *Drupal Commerce* module forcibly casts decimal quantity in
the *Shopping cart block* to integer value which is confusing and might mislead
a customer:

```
$count += (int) $order_item->getQuantity();
```

However, with the *Commerce Extended Quantity* module you may encounter the
following quantities in the *Shopping cart block*:

![Wrong quantities](images/wrong-quantities.png "Wrong quantities")

Again, the decimal quantity is truncated in the block. To mitigate the issue go
to the [admin/structure/views/view/commerce_cart_block](#0
"Cart block view") page, open *order item: Quantity* for editing and do the
following changes:

![Edit block view](images/edit-block-view.png "Edit block view")

And you'd get this:

![Right quantities](images/right-quantities.png "Right quantities")

## Have a good sales' quantity!

###### Module author:
```
  Vladimir Proshin (drugan)
  [proshins@gmail.com](proshins@gmail.com)
  [https://drupal.org/u/drugan](https://drupal.org/u/drugan)
```
