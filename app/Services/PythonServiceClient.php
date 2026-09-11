<?php

namespace App\Services;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class PythonServiceClient
{
    /**
     * Create the base HTTP client with internal key authentication.
     */
    protected function client(): PendingRequest
    {
        $baseUrl = config('services.python.url');
        $internalKey = config('services.python.key');

        if (! $baseUrl || ! $internalKey) {
            throw new RuntimeException('Python service URL or internal key not configured.');
        }

        return Http::baseUrl($baseUrl)
            ->withHeaders(['X-Internal-Key' => $internalKey])
            ->timeout(120)
            ->retry(2, 500);
    }

    /**
     * Profile a file and return the quality report.
     *
     * @param  string  $filePath  Relative path within the data-processing disk
     * @param  array  $options  Optional profiling options
     * @return array<string, mixed>
     */
    public function profile(string $filePath, array $options = []): array
    {
        return $this->postAndDecode('/profile', array_merge([
            'file_path' => $filePath,
        ], $options));
    }

    /**
     * Extract structured data from an unstructured file.
     *
     * @param  string  $filePath  Relative path within the data-processing disk
     * @return array<string, mixed>
     */
    public function extract(string $filePath): array
    {
        return $this->postAndDecode('/extract', [
            'file_path' => $filePath,
        ]);
    }

    /**
     * Apply cleaning steps to a dataset.
     *
     * @param  string  $filePath  Relative path within the data-processing disk
     * @param  array<int, array<string, mixed>>  $steps  Ordered cleaning step configs
     * @param  array  $options  Optional cleaning options
     * @return array<string, mixed>
     */
    public function clean(string $filePath, array $steps, array $options = []): array
    {
        return $this->postAndDecode('/clean', array_merge([
            'file_path' => $filePath,
            'steps' => $steps,
        ], $options));
    }

    /**
     * Validate a dataset against a schema contract.
     *
     * @param  string  $filePath  Relative path within the data-processing disk
     * @param  array<string, mixed>  $schemaDefinition  The contract's schema definition
     * @return array<string, mixed>
     */
    public function validate(string $filePath, array $schemaDefinition): array
    {
        return $this->postAndDecode('/validate', [
            'file_path' => $filePath,
            'schema_definition' => $schemaDefinition,
        ]);
    }

    /**
     * Export a dataset to the target format.
     *
     * @param  string  $filePath  Relative path within the data-processing disk
     * @param  string  $format  Target format (csv, parquet, json)
     * @param  array<string, mixed>|null  $schema  Optional contract schema for JSON export
     * @return array<string, mixed>
     */
    public function export(string $filePath, string $format, ?array $schema = null): array
    {
        return $this->postAndDecode('/export', array_filter([
            'file_path' => $filePath,
            'format' => $format,
            'options' => $schema,
        ]));
    }

    /**
     * Send a POST request and decode the JSON response.
     *
     * @param  string  $uri  Endpoint path
     * @param  array<string, mixed>  $data  Request body
     * @return array<string, mixed>
     *
     * @throws RuntimeException
     */
    protected function postAndDecode(string $uri, array $data): array
    {
        $response = $this->client()->post($uri, $data);

        if ($response->failed()) {
            throw new RuntimeException(
                "Python service request to {$uri} failed: {$response->status()} — {$response->body()}"
            );
        }

        return $response->json();
    }
}
