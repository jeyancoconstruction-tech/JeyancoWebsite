<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Project extends Model
{
    use HasFactory;

    // Mass assignable fields
    protected $fillable = [
        'name',
    ];

    // Relationship to Employee
    public function employees()
    {
        return $this->hasMany(Employee::class, 'project_id');
    }
}