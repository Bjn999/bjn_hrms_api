<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Traits\HasTenant;

class TenantModel extends Model
{
    use HasTenant;
}
