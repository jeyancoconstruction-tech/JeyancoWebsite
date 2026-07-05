<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LaborType extends Model
{
    protected $table = 'labor_types';

    protected $fillable = [
        'name',
        'daily_rate',
    ];

    protected $casts = [
        'daily_rate' => 'float',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function employees() {
        return $this->hasMany(Employee::class, 'labor_type_id');
    }

    // Get formatted daily rate
    public function getFormattedDailyRate()
    {
        return '₱' . number_format($this->daily_rate, 2);
    }

    // Calculate hourly rate (daily_rate / 8 hours)
    public function getHourlyRate()
    {
        return $this->daily_rate / 8;
    }

    // Get formatted hourly rate
    public function getFormattedHourlyRate()
    {
        return '₱' . number_format($this->getHourlyRate(), 2);
    }

    // Calculate OT rate (hourly_rate × 1.25 for regular overtime)
    public function getOTRate()
    {
        return $this->getHourlyRate() * 1.25;
    }

    // Get formatted OT rate
    public function getFormattedOTRate()
    {
        return '₱' . number_format($this->getOTRate(), 2);
    }

    /**
     * Calculate OT pay for given hours
     * @param float $hours Hours worked beyond 8 hours
     * @param string $dayType 'regular' (1.25x), 'restday' (1.30x), 'special_holiday' (1.30x), 'national_holiday' (1.50x)
     */
    public function calculateOTPay($hours, $dayType = 'regular')
    {
        $hourlyRate = $this->getHourlyRate();
        $multiplier = 1.25; // Default for regular OT
        
        switch ($dayType) {
            case 'restday':
                $multiplier = 1.30;
                break;
            case 'special_holiday':
                $multiplier = 1.30;
                break;
            case 'national_holiday':
                $multiplier = 1.50;
                break;
            case 'regular':
            default:
                $multiplier = 1.25;
                break;
        }
        
        return ($hourlyRate * $multiplier) * $hours;
    }
}