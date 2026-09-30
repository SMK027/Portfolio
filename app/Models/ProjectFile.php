<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\IsAttachment;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProjectFile extends Model
{
    use Auditable, IsAttachment;

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function url(): string
    {
        return route('projects.files.show', [$this->project_id, $this]);
    }

    public function downloadUrl(): string
    {
        return route('projects.files.show', [$this->project_id, $this, 'download' => 1]);
    }
}
