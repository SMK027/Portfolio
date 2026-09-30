<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\IsAttachment;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ArticleFile extends Model
{
    use Auditable, IsAttachment;

    public function article(): BelongsTo
    {
        return $this->belongsTo(Article::class);
    }

    public function url(): string
    {
        return route('articles.files.show', [$this->article_id, $this]);
    }

    public function downloadUrl(): string
    {
        return route('articles.files.show', [$this->article_id, $this, 'download' => 1]);
    }
}
