<?php

use App\Services\PythonServiceClient;
use Illuminate\Http\Client\Request;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    config(['services.python.url' => 'http://python-service.test']);
    config(['services.python.key' => 'secret-key']);

    $this->client = new PythonServiceClient;
});

test('profile endpoint is called correctly', function () {
    Http::fake([
        'http://python-service.test/profile' => Http::response([
            'summary' => ['row_count' => 100],
        ], 200),
    ]);

    $result = $this->client->profile('data/test.csv');

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
    $result = $this->client->clean('data/test.csv', $steps);

    expect($result['status'])->toBe('success');

    Http::assertSent(function (Request $request) use ($steps) {
        return $request->url() == 'http://python-service.test/clean' &&
               $request['file_path'] == 'data/test.csv' &&
               $request['steps'] == $steps;
    });
});

test('client throws exception on error response', function () {
    Http::fake([
        '*' => Http::response(['detail' => 'Invalid file format'], 422),
    ]);

    $this->client->profile('data/bad.xyz');
})->throws(RequestException::class);
