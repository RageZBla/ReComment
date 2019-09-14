<?php
declare(strict_types=1);

namespace App;

use Illuminate\Database\Eloquent\Model;

class Comment extends Model
{
    protected $fillable = [
        'comment',
    ];

    public function user()
    {
        return $this->hasOne(User::class);
    }

    public function likes()
    {
        return $this->belongsToMany(User::class, 'comment_like');
    }
}
