<?php

namespace App\Models;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
class FeedbackComment extends Model
{
    protected $fillable = [
        'user_id',
        'type',
        'faq_id',
        'faq_question',
        'comment',
        'status',
        'admin_response',
    ];
  

public function user(): BelongsTo
{
    return $this->belongsTo(User::class);
}
}
