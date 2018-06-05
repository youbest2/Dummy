<?php

namespace MyCLabs\Work\Adapter\RabbitMQ;

use Exception;
use MyCLabs\Work\Worker\WorkerEventTrait;
use MyCLabs\Work\Worker\WorkerTaskExecutorTrait;
use MyCLabs\Work\Task\Task;
use MyCLabs\Work\Worker\Worker;
use PhpAmqpLib\Channel\AMQPChannel;
use PhpAmqpLib\Message\AMQPMessage;

/**
 * RabbitMQ implementation.
 *
 * @author Matthieu Napoli <matthieu@mnapoli.fr>
 */
class RabbitMQWorker implements Worker
{
    use \MyCLabs\Work\Worker\WorkerEventTrait;
    use \MyCLabs\Work\Worker\WorkerTaskExecutorTrait;

    /**
     * @var AMQPChannel
     */
    private $channel;

    /**
     * @var string
     */
    private $queue;

    /**
     * @param AMQPChannel $channel
     * @param string      $queue
     */
    public function __construct(AMQPChannel $channel, $queue)
    {
        $this->channel = $channel;
        $this->queue = $queue;
    }

    /**
     * {@inheritdoc}
     */
    public function work($count = null)
    {
        $callback = function (AMQPMessage $message) {
            $this->taskHandler($message);
        };

        $this->channel->basic_qos(null, 1, null);
        $this->channel->basic_consume($this->queue, '', false, false, false, false, $callback);

        // Loop infinitely (or up to $count) to execute tasks
        while (count($this->channel->callbacks) && (is_null($count) || ($count > 0))) {
            $this->channel->wait();

            if (! is_null($count)) {
                $count--;
            }
        }
    }

    /**
     * Handles a task.
     *
     * @param mixed $message
     */
    private function taskHandler(AMQPMessage $message)
    {
        /** @var AMQPChannel $channel */
        $channel = $message->delivery_info['channel'];

        // Listen to the "reply_to" queue
        $replyExchange = null;
        $replyQueue = null;
        if ($message->has('reply_to')) {
            list($replyExchange, $replyQueue) = explode(';', $message->get('reply_to'));
        }

        /** @var Task $task */
        $task = unserialize($message->body);

        try {
            $this->triggerEvent(self::EVENT_AFTER_TASK_UNSERIALIZATION, [$task]);
            $this->triggerEvent(self::EVENT_BEFORE_TASK_EXECUTION, [$task]);

            // Execute the task
            $this->getExecutor($task)->execute($task);

            $this->triggerEvent(self::EVENT_BEFORE_TASK_FINISHED, [$task]);

            $success = true;
            $e = null;
        } catch (Exception $e) {
            $success = false;
        }

        // Signal the job status to RabbitMQ
        if ($success) {
            $channel->basic_ack($message->delivery_info['delivery_tag']);
        } else {
            $channel->basic_reject($message->delivery_info['delivery_tag'], false);
        }

        $dispatcherNotified = false;
        // Signal the job status to the dispatcher
        if ($replyExchange) {
            $message = ($success ? 'finished' : 'errored');
            $dispatcherNotified = $this->notifyDispatcher($replyExchange, $replyQueue, $message);
        }

        if ($success) {
            $this->triggerEvent(self::EVENT_ON_TASK_SUCCESS, [$task, $dispatcherNotified]);
        } else {
            $this->triggerEvent(self::EVENT_ON_TASK_ERROR, [$task, $e, $dispatcherNotified]);
        }
    }

    /**
     * Signal to the emitter of the task that we finished.
     *
     * @param string $exchange
     * @param string $queue
     * @param string $messageContent Message to send to the dispatcher.
     *
     * @return bool
     */
    private function notifyDispatcher($exchange, $queue, $messageContent)
    {
        // We put in the queue that we finished
        $this->channel->basic_publish(new AMQPMessage($messageContent), $exchange);

        // Read the first message coming out of the queue
        $message = $this->waitForMessage($queue, 0.5);

        if (! $message) {
            // Shouldn't happen -> error while delivering messages?
            return false;
        }

        // If the first message of the queue is our message, we can die in peace
        // (else it would be the "timeout" message from the dispatcher)
        $dispatcherNotified = ($message->body == $messageContent);

        // Delete our queue
        $this->channel->queue_delete($queue);

        return $dispatcherNotified;
    }

    /**
     * Read a queue until there's a message or until a timeout.
     *
     * @param string $queue
     * @param int    $timeout Time to wait in seconds
     * @return AMQPMessage|null
     */
    private function waitForMessage($queue, $timeout)
    {
        $timeStart = microtime(true);

        do {
            // Get message and auto-ack
            $response = $this->channel->basic_get($queue);
            if ($response) {
                return $response;
            }

            // Sleep 300 ms
            usleep(300000);
            $timeSpent = microtime(true) - $timeStart;
        } while ($timeSpent < $timeout);

        return null;
    }
}
