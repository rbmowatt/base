<?php

namespace Example\Models;

use RBMowatt\Base\Models\BaseModel;

class WidgetType extends BaseModel
{
    protected $table = 'widget_types';

    protected $fillable = ['name'];
}
