<?php

namespace Illuminate\Queue\Console;

use Illuminate\Support\Arr;
use Illuminate\Console\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;

class RetryCommand extends Command
{
    /**
     * The console command name.
     *
     * @var string
     */
    protected $name = 'queue:retry';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Retry a failed queue job';

    /**
     * Execute the console command.
     *
     * @return void
     */
    public function fire()
    {
        ($this->option('queue')) ? $this->processFailedJobsByQueue() : $this->processFailedJobsByIds();
    }

    /**
     * Process failed jobs by Ids
     *
     * @return array
     */
    protected function processFailedJobsByIds(){
        foreach ($this->getJobIds() as $id) {
            $job = $this->laravel['queue.failer']->find($id);

            if (is_null($job)) {
                $this->error("Unable to find failed job with ID [{$id}].");
            } else {
                $this->retryJob($job);
                $this->info("The failed job [{$id}] has been pushed back onto the queue!");
                $this->laravel['queue.failer']->forget($id);
            }
        }
    }

    /**
     * Process failed jobs by queue name
     *
     * @return array
     */
    protected function processFailedJobsByQueue(){
        $queue = $this->option('queue');
        $jobs = collect($this->laravel['queue.failer']->all())->where("queue", $queue)->all();
        if (is_null($jobs)) {
            $this->error("Unable to find failed jobs with queue name [{$queue}].");
        }
        foreach ($jobs as $job) {
            $this->retryJob($job);
            $this->info("The failed job [{$queue}] has been pushed back onto the queue!");
            $this->laravel['queue.failer']->forget($job->id);
        }
    }

    /**
     * Get the job IDs to be retried.
     *
     * @return array
     */
    protected function getJobIds()
    {
        $ids = $this->argument('id');

        if (count($ids) === 1 && $ids[0] === 'all') {
            $ids = Arr::pluck($this->laravel['queue.failer']->all(), 'id');
        }

        return $ids;
    }

    /**
     * Retry the queue job.
     *
     * @param  \stdClass  $job
     * @return void
     */
    protected function retryJob($job)
    {
        $this->laravel['queue']->connection($job->connection)->pushRaw(
            $this->resetAttempts($job->payload), $job->queue
        );
    }

    /**
     * Reset the payload attempts.
     *
     * Applicable to Redis jobs which store attempts in their payload.
     *
     * @param  string  $payload
     * @return string
     */
    protected function resetAttempts($payload)
    {
        $payload = json_decode($payload, true);

        if (isset($payload['attempts'])) {
            $payload['attempts'] = 0;
        }

        return json_encode($payload);
    }

    /**
     * Get the console command arguments.
     *
     * @return array
     */
    protected function getArguments()
    {
        return [
            ['id', InputArgument::IS_ARRAY, 'The ID of the failed job'],
        ];
    }

    /**
     * Get the console command options.
     *
     * @return array
     */
    protected function getOptions()
    {
        return [
            ['queue', null, InputOption::VALUE_OPTIONAL, 'The queue name of the failed jobs', null]
        ];
    }
}
