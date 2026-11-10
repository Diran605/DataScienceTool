<?php

use App\Services\PythonServiceClient;
use Illuminate\Http\Client\Request;
use Illuminate\Http\Client\RequestException;
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

test('client throws exception on error response', function () {
    Http::fake([
        '*' => Http::response(['detail' => 'Invalid file format'], 422),
    ]);

    pythonClient()->profile('data/bad.xyz');
})->throws(RequestException::class);
