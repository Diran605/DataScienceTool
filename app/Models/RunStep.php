<?php

namespace App\Models;

use App\Enums\StepType;
use Database\Factories\RunStepFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['run_id', 'step_type', 'config', 'result', 'status'])]
class RunStep extends Model
{
    /** @use HasFactory<RunStepFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'step_type' => StepType::class,
            'config' => 'array',
            'result' => 'array',
        ];
    }

    /**
     * The run this step belongs to.
     */
    public function run(): BelongsTo
    {
        return $this->belongsTo(Run::class);
    }
}
