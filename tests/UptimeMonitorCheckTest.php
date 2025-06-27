<?php

use BilalHaidar\UptimeMonitorHealthCheck\UptimeMonitorCheck;
use Spatie\UptimeMonitor\Models\Monitor;
use Spatie\Health\Enums\Status; // Import the Status enum

beforeEach(function () {
    // Clear mocks before each test to ensure a clean state
    Mockery::close();
});

test('it succeeds when no monitors are configured', function () {
    // Mock the Monitor::all() method to return an empty collection
    Mockery::mock('alias:' . Monitor::class)
        ->shouldReceive('all')
        ->andReturn(collect([]));

    $check = new UptimeMonitorCheck();
    $result = $check->run();

    // Assert that the result status is OK
    expect($result->status)->toBe(Status::ok())
        // Assert the short summary matches the expected value from the Canvas
        ->and($result->shortSummary)->toBe('No monitors configured')
        // Assert the meta data matches the expected value from the Canvas
        ->and($result->meta)->toBe([
            'total_monitors' => 0,
            'status' => 'ok',
        ]);
});

test('it succeeds when all monitors are up', function () {
    // Create mock monitor objects for "up" status
    $monitor1 = (object)[
        'url' => 'https://monitor1.com',
        'name' => 'Monitor One', // Added name for comprehensive meta data
        'status' => 'up',
        'isDown' => false,
        'isFailed' => false,
        'isNotYetChecked' => false,
        'last_check_status' => 'succeeded', // Added for meta data
    ];
    $monitor2 = (object)[
        'url' => 'https://monitor2.com',
        'name' => 'Monitor Two', // Added name for comprehensive meta data
        'status' => 'up',
        'isDown' => false,
        'isFailed' => false,
        'isNotYetChecked' => false,
        'last_check_status' => 'succeeded', // Added for meta data
    ];

    // Mock Monitor::all() to return a collection of "up" monitors
    Mockery::mock('alias:' . Monitor::class)
        ->shouldReceive('all')
        ->andReturn(collect([$monitor1, $monitor2]));

    $check = new UptimeMonitorCheck();
    $result = $check->run();

    // Assert that the result status is OK
    expect($result->status)->toBe(Status::ok())
        // Assert the short summary matches the expected value from the Canvas
        ->and($result->shortSummary)->toBe('All 2 monitors up')
        // Assert the meta data matches the expected values from the Canvas
        ->and($result->meta)->toBe([
            'total_monitors' => 2,
            'up_monitors' => 2,
            'down_monitors' => 0,
            'failed_monitors' => 0,
            'not_checked_monitors' => 0,
            'down_urls' => [],
            'failed_urls' => [],
            'not_checked_urls' => [],
            'status' => 'ok',
        ]);
});

test('it warns when some monitors have not been checked yet', function () {
    // Create mock monitor objects with one "not yet checked"
    $monitor1 = (object)[
        'url' => 'https://monitor1.com',
        'name' => 'Monitor One',
        'status' => 'up',
        'isDown' => false,
        'isFailed' => false,
        'isNotYetChecked' => false,
        'last_check_status' => 'succeeded',
    ];
    $monitor2 = (object)[
        'url' => 'https://monitor2.com',
        'name' => 'Monitor Two',
        'status' => 'not yet checked',
        'isDown' => false,
        'isFailed' => false,
        'isNotYetChecked' => true,
        'last_check_status' => null, // Not yet checked, so no last status
    ];

    // Mock Monitor::all() to return the mixed collection
    Mockery::mock('alias:' . Monitor::class)
        ->shouldReceive('all')
        ->andReturn(collect([$monitor1, $monitor2]));

    $check = new UptimeMonitorCheck();
    $result = $check->run();

    // Assert that the result status is Warning
    expect($result->status)->toBe(Status::warning())
        // Assert the short summary matches the expected value from the Canvas
        ->and($result->shortSummary)->toBe('1 not checked, 1 up')
        // Assert the meta data matches the expected values from the Canvas
        ->and($result->meta)->toBe([
            'total_monitors' => 2,
            'up_monitors' => 1,
            'down_monitors' => 0,
            'failed_monitors' => 0,
            'not_checked_monitors' => 1,
            'down_urls' => [],
            'failed_urls' => [],
            'not_checked_urls' => ['https://monitor2.com'],
            'status' => 'warning',
        ]);
});

test('it fails when some monitors are down', function () {
    // Create mock monitor objects with one "down" monitor
    $monitor1 = (object)[
        'url' => 'https://monitor1.com',
        'name' => 'Monitor One',
        'status' => 'up',
        'isDown' => false,
        'isFailed' => false,
        'isNotYetChecked' => false,
        'last_check_status' => 'succeeded',
    ];
    $monitor2 = (object)[
        'url' => 'https://monitor2.com',
        'name' => 'Monitor Two',
        'status' => 'down',
        'isDown' => true,
        'isFailed' => false,
        'isNotYetChecked' => false,
        'last_check_status' => 'failed',
        'latest_check_failure_message' => 'Connection refused', // Added for failure message
    ];

    // Mock Monitor::all() to return the mixed collection
    Mockery::mock('alias:' . Monitor::class)
        ->shouldReceive('all')
        ->andReturn(collect([$monitor1, $monitor2]));

    $check = new UptimeMonitorCheck();
    $result = $check->run();

    // Assert that the result status is Failed
    expect($result->status)->toBe(Status::failed())
        // Assert the short summary matches the expected value from the Canvas
        ->and($result->shortSummary)->toBe('1 down/failed, 1 up')
        // Assert the meta data matches the expected values from the Canvas
        ->and($result->meta)->toBe([
            'total_monitors' => 2,
            'up_monitors' => 1,
            'down_monitors' => 1,
            'failed_monitors' => 0,
            'not_checked_monitors' => 0,
            'down_urls' => ['https://monitor2.com'],
            'failed_urls' => [],
            'not_checked_urls' => [],
            'status' => 'failed',
        ]);
});

test('it fails when some monitors have failed status', function () {
    // Create mock monitor objects with one "failed" monitor
    $monitor1 = (object)[
        'url' => 'https://monitor1.com',
        'name' => 'Monitor One',
        'status' => 'up',
        'isDown' => false,
        'isFailed' => false,
        'isNotYetChecked' => false,
        'last_check_status' => 'succeeded',
    ];
    $monitor2 = (object)[
        'url' => 'https://monitor2.com',
        'name' => 'Monitor Two',
        'status' => 'failed',
        'isDown' => false,
        'isFailed' => true,
        'isNotYetChecked' => false,
        'last_check_status' => 'failed',
        'latest_check_failure_message' => 'SSL certificate expired', // Added for failure message
    ];

    // Mock Monitor::all() to return the mixed collection
    Mockery::mock('alias:' . Monitor::class)
        ->shouldReceive('all')
        ->andReturn(collect([$monitor1, $monitor2]));

    $check = new UptimeMonitorCheck();
    $result = $check->run();

    // Assert that the result status is Failed
    expect($result->status)->toBe(Status::failed())
        // Assert the short summary matches the expected value from the Canvas
        ->and($result->shortSummary)->toBe('1 down/failed, 1 up')
        // Assert the meta data matches the expected values from the Canvas
        ->and($result->meta)->toBe([
            'total_monitors' => 2,
            'up_monitors' => 1,
            'down_monitors' => 0,
            'failed_monitors' => 1,
            'not_checked_monitors' => 0,
            'down_urls' => [],
            'failed_urls' => ['https://monitor2.com'],
            'not_checked_urls' => [],
            'status' => 'failed',
        ]);
});

test('it can check a specific monitor by url and it is up', function () {
    // Create a mock monitor object for a specific "up" monitor
    $monitor = (object)[
        'url' => 'https://specific.com/monitor',
        'name' => 'Specific Monitor',
        'status' => 'up',
        'isDown' => false,
        'isFailed' => false,
        'isNotYetChecked' => false,
        'last_check_status' => 'succeeded',
        'latest_check_failure_message' => null,
    ];

    // Mock the query builder methods for finding a specific monitor
    Mockery::mock('alias:' . Monitor::class)
        ->shouldReceive('query')->andReturnSelf()
        ->shouldReceive('where')->with('url', 'https://specific.com/monitor')->andReturnSelf()
        ->shouldReceive('orWhere')->with('name', 'https://specific.com/monitor')->andReturnSelf()
        ->shouldReceive('first')->andReturn($monitor);

    $check = UptimeMonitorCheck::make()->monitor('https://specific.com/monitor');
    $result = $check->run();

    // Assert that the result status is OK
    expect($result->status)->toBe(Status::ok())
        // Assert the short summary matches the expected value from the Canvas
        ->and($result->shortSummary)->toBe("Monitor 'https://specific.com/monitor' is up")
        // Assert the meta data matches the expected values from the Canvas
        ->and($result->meta)->toBe([
            'monitor_url' => 'https://specific.com/monitor',
            'monitor_name' => 'Specific Monitor',
            'status' => 'up',
            'last_check_status' => 'succeeded',
        ]);
});

test('it can check a specific monitor by url and it is down', function () {
    // Create a mock monitor object for a specific "down" monitor
    $monitor = (object)[
        'url' => 'https://specific.com/down',
        'name' => 'Specific Down Monitor',
        'status' => 'down',
        'isDown' => true,
        'isFailed' => false,
        'isNotYetChecked' => false,
        'last_check_status' => 'failed',
        'latest_check_failure_message' => 'HTTP 500 error',
    ];

    // Mock the query builder methods for finding a specific monitor
    Mockery::mock('alias:' . Monitor::class)
        ->shouldReceive('query')->andReturnSelf()
        ->shouldReceive('where')->andReturnSelf()
        ->shouldReceive('orWhere')->andReturnSelf()
        ->shouldReceive('first')->andReturn($monitor);

    $check = UptimeMonitorCheck::make()->monitor('https://specific.com/down');
    $result = $check->run();

    // Assert that the result status is Failed
    expect($result->status)->toBe(Status::failed())
        // Assert the short summary matches the expected value from the Canvas
        ->and($result->shortSummary)->toBe("Monitor 'https://specific.com/down' is down")
        // Assert the meta data matches the expected values from the Canvas
        ->and($result->meta)->toBe([
            'monitor_url' => 'https://specific.com/down',
            'monitor_name' => 'Specific Down Monitor',
            'status' => 'down',
            'last_check_status' => 'failed',
            'latest_check_failure_message' => 'HTTP 500 error',
        ]);
});

test('it fails when specific monitor is not found', function () {
    // Mock the query builder methods for a non-existent monitor
    Mockery::mock('alias:' . Monitor::class)
        ->shouldReceive('query')->andReturnSelf()
        ->shouldReceive('where')->andReturnSelf()
        ->shouldReceive('orWhere')->andReturnSelf()
        ->shouldReceive('first')->andReturn(null);

    $check = UptimeMonitorCheck::make()->monitor('https://non-existent.com');
    $result = $check->run();

    // Assert that the result status is Failed
    expect($result->status)->toBe(Status::failed())
        // Assert the short summary matches the expected value from the Canvas
        ->and($result->shortSummary)->toBe("Monitor 'https://non-existent.com' not found")
        // Assert the meta data matches the expected values from the Canvas
        ->and($result->meta)->toBe([
            'monitor_identifier' => 'https://non-existent.com',
            'status' => 'not found',
        ]);
});

test('it fails when a monitor is down and not yet checked mix', function () {
    // Create mock monitor objects with a mix of statuses
    $monitor1 = (object)[
        'url' => 'https://monitor1.com',
        'name' => 'Monitor One',
        'status' => 'down',
        'isDown' => true,
        'isFailed' => false,
        'isNotYetChecked' => false,
        'last_check_status' => 'failed',
        'latest_check_failure_message' => 'Host unreachable',
    ];
    $monitor2 = (object)[
        'url' => 'https://monitor2.com',
        'name' => 'Monitor Two',
        'status' => 'not yet checked',
        'isDown' => false,
        'isFailed' => false,
        'isNotYetChecked' => true,
        'last_check_status' => null,
    ];
    $monitor3 = (object)[
        'url' => 'https://monitor3.com',
        'name' => 'Monitor Three',
        'status' => 'up',
        'isDown' => false,
        'isFailed' => false,
        'isNotYetChecked' => false,
        'last_check_status' => 'succeeded',
    ];

    // Mock Monitor::all() to return the mixed collection
    Mockery::mock('alias:' . Monitor::class)
        ->shouldReceive('all')
        ->andReturn(collect([$monitor1, $monitor2, $monitor3]));

    $check = new UptimeMonitorCheck();
    $result = $check->run();

    // Assert that the result status is Failed
    expect($result->status)->toBe(Status::failed())
        // Assert the short summary matches the expected value from the Canvas
        ->and($result->shortSummary)->toBe('1 down/failed, 1 up')
        // Assert the meta data matches the expected values from the Canvas
        ->and($result->meta)->toBe([
            'total_monitors' => 3,
            'up_monitors' => 1,
            'down_monitors' => 1,
            'failed_monitors' => 0,
            'not_checked_monitors' => 1,
            'down_urls' => ['https://monitor1.com'],
            'failed_urls' => [],
            'not_checked_urls' => ['https://monitor2.com'],
            'status' => 'failed',
        ]);
});
