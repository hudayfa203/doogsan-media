<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ContactMessage extends Model
{
    protected $fillable = ['locale', 'name', 'email', 'subject', 'department', 'message', 'status', 'ip_hash'];
}
