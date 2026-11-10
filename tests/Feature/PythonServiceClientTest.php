<?php

use App\Services\PythonServiceClient;
use App\Services\PythonServiceException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    config(['services.python.url' => 'http://python-service.test']);
    config(['services.python.key' => 'secret-key']);
});

function pythonClient(): PythonServiceClient
{
    return new PythonServiceClient;
}

test('profile endpoint is called correctly', function () {
    Http::fake([
        'http://python-service.test/profile' => Http::response([
            'summary' => ['row_count' => 100],
        ], 200),
    ]);

    $result = pythonClient()->profile('data/test.csv');

    expect($result)->toHaveKey('summary')
        ->and($result['summary']['row_count'])->toBe(100);

    Http::assertSent(function (Request $request) {
        return $request->hasHeader('X-Internal-Key', 'secret-key') &&
               $request->url() == 'http://python-service.test/profile' &&
               $request['file_path'] == 'data/test.csv';
    });
});

test('clean endpoint sends correct payload', function () {
    Http::fake([
        'http://python-service.test/clean' => Http::response([
            'status' => 'success',
        ], 200),
    ]);

    $steps = [['action' => 'drop_nulls']];
    $result = pythonClient()->clean('data/test.csv', $steps);

    expect($result['status'])->toBe('success');

    Http::assertSent(function (Request $request) use ($steps) {
        return $request->url() == 'http://python-service.test/clean' &&
               $request['file_path'] == 'data/test.csv' &&
               $request['steps'] == $steps;
    });
});

test('clean sends options nested under the options key', function () {
    Http::fake(['http://python-service.test/clean' => Http::response(['status' => 'success'], 200)]);

    pythonClient()->clean('data/test.csv', [['action' => 'deduplicate']], ['preview_only' => true]);

    Http::assertSent(fn (Request $request) => $request['options'] === ['preview_only' => true]
        && ! isset($request->data()['preview_only']));
});

test('clean omits options when there are none', function () {
    Http::fake(['http://python-service.test/clean' => Http::response(['status' => 'success'], 200)]);

    pythonClient()->clean('data/test.csv', [['action' => 'deduplicate']]);

    Http::assertSent(fn (Request $request) => ! array_key_exists('options', $request->data()));
});

test('validate sends the contract as schema_definition', function () {
    Http::fake(['http://python-service.test/validate' => Http::response(['is_valid' => true, 'errors' => []], 200)]);

    $schema = ['columns' => [['name' => 'id', 'type' => 'integer']]];
    $result = pythonClient()->validate('data/test.csv', $schema);

    expect($result['is_valid'])->toBeTrue();

    Http::assertSent(fn (Request $request) => $request->url() === 'http://python-service.test/validate'
        && $request->data() === ['file_path' => 'data/test.csv', 'schema_definition' => $schema]);
});

test('extract sends only the file path', function () {
    Http::fake(['http://python-service.test/extract' => Http::response(['status' => 'success'], 200)]);

    pythonClient()->extract('data/test.pdf');

    Http::assertSent(fn (Request $request) => $request->data() === ['file_path' => 'data/test.pdf']);
});

test('an error response throws a non-retryable exception with the service detail, without retrying', function () {
    Http::fake(['*' => Http::response(['detail' => 'Invalid file format'], 400)]);

    expect(fn () => pythonClient()->profile('data/bad.xyz'))
        ->toThrow(fn (PythonServiceException $e) => expect($e->status)->toBe(400)
            ->and($e->isRetryable())->toBeFalse()
            ->and($e->getMessage())->toContain('Invalid file format'));

    Http::assertSentCount(1);
});

test('gateway errors are retryable but not retried by the http client itself', function (int $status) {
    Http::fake(['*' => Http::response('Bad gateway', $status)]);

    expect(fn () => pythonClient()->profile('data/test.csv'))
        ->toThrow(fn (PythonServiceException $e) => expect($e->isRetryable())->toBeTrue());

    Http::assertSentCount(1);
})->with([502, 503, 504]);

test('an unreachable service is reported as retryable', function () {
    Http::fake(['*' => Http::failedConnection('Connection refused')]);

    expect(fn () => pythonClient()->profile('data/test.csv'))
        ->toThrow(fn (PythonServiceException $e) => expect($e->isRetryable())->toBeTrue()
            ->and($e->getMessage())->toContain('unreachable'));
});

test('a non-json success response is rejected', function () {
    Http::fake(['*' => Http::response('<html>proxy page</html>', 200)]);

    expect(fn () => pythonClient()->profile('data/test.csv'))
        ->toThrow(PythonServiceException::class, 'invalid response');
});
