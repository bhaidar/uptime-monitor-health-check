<?php

namespace BilalHaidar\UptimeMonitorHealthCheck;

use Spatie\Health\Checks\Check;
use Spatie\Health\Checks\Result;
use Spatie\UptimeMonitor\Models\Monitor;

class UptimeMonitorCheck extends Check
{
    protected ?string $specificMonitorUrl = null;

    /**
     * Static make() method for fluent configuration.
     */
    public static function make(): static
    {
        return new static;
    }

    /**
     * Specify a monitor by its URL or name to check only that specific monitor.
     *
     * @param  string  $url  The URL or name of the specific monitor to check.
     * @return $this
     */
    public function monitor(string $url): self
    {
        $this->specificMonitorUrl = $url;

        return $this;
    }

    /**
     * Run the health check. This method determines whether to check a specific monitor
     * or all configured monitors and returns the result.
     *
     * @return Result The result of the health check.
     */
    public function run(): Result
    {
        $result = Result::make();

        if ($this->specificMonitorUrl) {
            return $this->checkSpecificMonitor($result);
        }

        return $this->checkAllMonitors($result);
    }

    /**
     * Check a specific monitor identified by its URL or name.
     *
     * @param  Result  $result  The initial Result object.
     * @return Result The updated Result object indicating the status of the specific monitor.
     */
    protected function checkSpecificMonitor(Result $result): Result
    {
        // Attempt to find the monitor by URL or name
        $monitor = Monitor::query()
            ->where('url', $this->specificMonitorUrl)
            ->orWhere('name', $this->specificMonitorUrl)
            ->first();

        // If the monitor is not found, return a failed result
        if (! $monitor) {
            return $result
                ->failed("Monitor '{$this->specificMonitorUrl}' not found.")
                ->shortSummary("Monitor '{$this->specificMonitorUrl}' not found")
                ->meta([
                    'monitor_identifier' => $this->specificMonitorUrl,
                    'status' => 'not found',
                ]);
        }

        // Check the status of the found monitor
        if ($monitor->isDown) {
            return $result
                ->failed("Monitor '{$this->specificMonitorUrl}' is down. Last check failure message: {$monitor->latest_check_failure_message}")
                ->shortSummary("Monitor '{$this->specificMonitorUrl}' is down")
                ->meta([
                    'monitor_url' => $monitor->url,
                    'monitor_name' => $monitor->name,
                    'status' => 'down',
                    'last_check_status' => $monitor->last_check_status,
                    'latest_check_failure_message' => $monitor->latest_check_failure_message,
                ]);
        }

        if ($monitor->isFailed) {
            return $result
                ->failed("Monitor '{$this->specificMonitorUrl}' has failed. Last check failure message: {$monitor->latest_check_failure_message}")
                ->shortSummary("Monitor '{$this->specificMonitorUrl}' has failed")
                ->meta([
                    'monitor_url' => $monitor->url,
                    'monitor_name' => $monitor->name,
                    'status' => 'failed',
                    'last_check_status' => $monitor->last_check_status,
                    'latest_check_failure_message' => $monitor->latest_check_failure_message,
                ]);
        }

        if ($monitor->isNotYetChecked) {
            return $result
                ->warning("Monitor '{$this->specificMonitorUrl}' has not been checked yet.")
                ->shortSummary("Monitor '{$this->specificMonitorUrl}' not checked yet")
                ->meta([
                    'monitor_url' => $monitor->url,
                    'monitor_name' => $monitor->name,
                    'status' => 'not checked',
                    'last_check_status' => $monitor->last_check_status,
                ]);
        }

        // If none of the above, the monitor is up and healthy
        return $result
            ->ok("Monitor '{$this->specificMonitorUrl}' is up.")
            ->shortSummary("Monitor '{$this->specificMonitorUrl}' is up")
            ->meta([
                'monitor_url' => $monitor->url,
                'monitor_name' => $monitor->name,
                'status' => 'up',
                'last_check_status' => $monitor->last_check_status,
            ]);
    }

    /**
     * Check the health of all configured monitors.
     *
     * @param  Result  $result  The initial Result object.
     * @return Result The updated Result object indicating the aggregated status of all monitors.
     */
    protected function checkAllMonitors(Result $result): Result
    {
        $monitors = Monitor::all();

        // If no monitors are configured, return an OK result
        if ($monitors->isEmpty()) {
            return $result
                ->ok('No uptime monitors configured.')
                ->shortSummary('No monitors configured')
                ->meta([
                    'total_monitors' => 0,
                    'status' => 'ok',
                ]);
        }

        $downMonitors = [];
        $failedMonitors = [];
        $notCheckedMonitors = [];
        $upMonitors = [];

        // Categorize monitors based on their status
        foreach ($monitors as $monitor) {
            if ($monitor->isDown) {
                $downMonitors[] = $monitor->url;
            } elseif ($monitor->isFailed) {
                $failedMonitors[] = $monitor->url;
            } elseif ($monitor->isNotYetChecked) {
                $notCheckedMonitors[] = $monitor->url;
            } else {
                $upMonitors[] = $monitor->url;
            }
        }

        $totalMonitors = $monitors->count();
        $meta = [
            'total_monitors' => $totalMonitors,
            'up_monitors' => count($upMonitors),
            'down_monitors' => count($downMonitors),
            'failed_monitors' => count($failedMonitors),
            'not_checked_monitors' => count($notCheckedMonitors),
            'down_urls' => $downMonitors,
            'failed_urls' => $failedMonitors,
            'not_checked_urls' => $notCheckedMonitors,
        ];

        // If there are any down or failed monitors, return a failed result
        if (! empty($downMonitors) || ! empty($failedMonitors)) {
            $problematic = array_merge($downMonitors, $failedMonitors);
            $message = 'The following monitors are down or failed: '.implode(', ', $problematic);
            $shortSummary = sprintf('%d down/failed, %d up', count($problematic), count($upMonitors));

            return $result
                ->failed($message)
                ->shortSummary($shortSummary)
                ->meta(array_merge($meta, ['status' => 'failed']));
        }

        // If there are monitors that have not been checked yet, return a warning result
        if (! empty($notCheckedMonitors)) {
            $message = 'The following monitors have not been checked yet: '.implode(', ', $notCheckedMonitors);
            $shortSummary = sprintf('%d not checked, %d up', count($notCheckedMonitors), count($upMonitors));

            return $result
                ->warning($message)
                ->shortSummary($shortSummary)
                ->meta(array_merge($meta, ['status' => 'warning']));
        }

        // If all monitors are up and healthy, return an OK result
        $shortSummary = sprintf('All %d monitors up', $totalMonitors);

        return $result
            ->ok('All uptime monitors are up and healthy.')
            ->shortSummary($shortSummary)
            ->meta(array_merge($meta, ['status' => 'ok']));
    }
}
