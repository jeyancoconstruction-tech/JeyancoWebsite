<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Payroll extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'date', 
        'hours',
        'rate',
        'ot',
        'sss',
        'philhealth',
        'pagibig',
        'vale',
        'week_range'
    ];
}