<?php

declare(strict_types=1);

namespace App\Models;

use App\Domain\Config\TasksToPerform\Enums\TaskDocumentType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class TaskToPerform extends Model
{
    use SoftDeletes;

    protected $table = 'tasks_to_perform';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'title',
        'description',
        'is_completed',
        'document_type',
        'document_id',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_completed' => 'boolean',
            'document_type' => TaskDocumentType::class,
            'document_id' => 'integer',
        ];
    }
}
