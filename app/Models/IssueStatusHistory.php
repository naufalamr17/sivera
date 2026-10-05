<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['issue_id', 'from_status', 'to_status', 'changed_by'])]
class IssueStatusHistory extends Model
{
    use HasFactory;

    public $timestamps = false;
}
