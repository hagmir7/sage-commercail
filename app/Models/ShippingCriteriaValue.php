<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\Pivot;

class ShippingCriteriaValue extends Pivot
{
     protected $fillable = [ 'shipping_id', 'shipping_criteria_id', 'status', 'note'];
}
