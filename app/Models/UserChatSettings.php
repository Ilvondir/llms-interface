<?php

namespace App\Models;

use Database\Factories\UserChatSettingsFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserChatSettings extends Model
{
    /** @use HasFactory<UserChatSettingsFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'api_base_url',
        'default_params',
        'active_conversation_id',
    ];

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'api_base_url' => '',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'default_params' => 'array',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<Conversation, $this>
     */
    public function activeConversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class, 'active_conversation_id');
    }
}
