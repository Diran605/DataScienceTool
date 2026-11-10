<?php

namespace App\Models;

use App\Enums\RunStatus;
use App\Enums\StepType;
use Database\Factories\RunFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['source_file_path', 'current_file_path', 'source_file_type', 'status', 'created_by'])]
class Run extends Model
{
    /** @use HasFactory<RunFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => RunStatus::class,
        ];
    }

    /**
     * The user who created this run.
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Steps associated with this run.
     */
    public function steps(): HasMany
    {
        return $this->hasMany(RunStep::class);
    }

    /**
     * Deliveries produced from this run.
     */
    public function deliveries(): HasMany
    {
        return $this->hasMany(Delivery::class);
    }

    /**
     * The file downstream steps (profile, validate, export) operate on: the latest
     * cleaned output if there is one, otherwise the original upload. The original
     * upload in source_file_path is never overwritten.
     */
    public function workingFilePath(): string
    {
        return $this->current_file_path ?? $this->source_file_path;
    }

    /**
     * Atomically move the run to $to, but only if it is currently in one of $from.
     *
     * This is a single conditional UPDATE, so when two requests race (e.g. a
     * double-clicked button) exactly one of them wins and the other gets false.
     *
     * @param  array<int, RunStatus>  $from
     */
    public function claim(array $from, RunStatus $to): bool
    {
        $claimed = static::query()
            ->whereKey($this->getKey())
            ->whereIn('status', array_map(fn (RunStatus $status) => $status->value, $from))
            ->update(['status' => $to, 'updated_at' => now()]) === 1;

        if ($claimed) {
            $this->status = $to;
            $this->syncOriginalAttribute('status');
        }

        return $claimed;
    }

    /**
     * The most recent completed step of the given type. Ordered by id rather than
     * timestamp so steps created within the same second are still ordered correctly.
     */
    public function latestCompletedStep(StepType $type): ?RunStep
    {
        return $this->steps()
            ->where('step_type', $type)
            ->where('status', 'completed')
            ->latest('id')
            ->first();
    }

    /**
     * Whether the current working data has passed validation, optionally against a
     * specific contract. A validation only counts if no cleaning was applied after it.
     */
    public function hasPassedValidation(?SchemaContract $contract = null): bool
    {
        $validation = $this->latestCompletedStep(StepType::Validate);

        if (! $validation || ! ($validation->result['is_valid'] ?? false)) {
            return false;
        }

        if ($contract && (int) ($validation->config['schema_contract_id'] ?? 0) !== $contract->id) {
            return false;
        }

        // Previews are stored as completed clean steps too, but produce no output file.
        $lastAppliedClean = $this->steps()
            ->where('step_type', StepType::Clean)
            ->where('status', 'completed')
            ->whereNotNull('output_file_path')
            ->latest('id')
            ->first();

        return ! $lastAppliedClean || $lastAppliedClean->id < $validation->id;
    }
}
