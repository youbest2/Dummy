# Listening for events

You can listen to events of

- the work dispatcher (i.e. in the web request)
- the worker (i.e. in the worker process)

## Work dispatcher events

Example:

```php
class MyEventListener implements \MyCLabs\Work\Dispatcher\Event\DispatcherEventListener
{
    /**
     * Event called before a task is dispatched to be run by a worker.
     *
     * You can use this event to prepare the task to be sent.
     *
     * @param Task $task
     */
    public function beforeTaskDispatched(Task $task)
    {
        // ...
    }

    /**
     * Event called before a task is serialized.
     *
     * You can use this event to prepare the task to be serialized.
     *
     * @param Task $task
     */
    public function beforeTaskSerialization(Task $task)
    {
        // ...
    }
}
```

## Worker events

Example:

```php
class MyEventListener extends \MyCLabs\Work\Worker\Event\WorkerEventListener
{
    /**
     * Event called after a task is unserialized.
     *
     * You can use this event to restore the state of the task.
     *
     * @param Task $task
     */
    public function afterTaskUnserialization(Task $task)
    {
        // ...
    }

    /**
     * Event called before a task is executed.
     *
     * If an exception is thrown in this method, then the task will be considered as errored
     * and the onTaskException event will be called.
     *
     * @param Task $task
     */
    public function beforeTaskExecution(Task $task)
    {
        // ...
    }

    /**
     * Event called after a task is executed (without error).
     *
     * The task is still not considered finished at this point.
     *
     * If an exception is thrown in this method, then the task will be considered as errored
     * and the onTaskException event will be called.
     *
     * @codeCoverageIgnore
     *
     * @param Task $task
     */
    public function beforeTaskFinished(Task $task)
    {
        // ...
    }

    /**
     * Event called after a task is executed successfully. The task has finished at this point.
     *
     * If an exception is thrown in this method, the worker will blow up!
     *
     * @codeCoverageIgnore
     *
     * @param Task $task
     * @param bool $dispatcherNotified If true, then the dispatcher of the task was waiting for the task
     *                                 to execute and was notified that it finished. If false, either
     *                                 the dispatcher wasn't waiting, either it stopped waiting after some time.
     */
    public function onTaskSuccess(Task $task, $dispatcherNotified)
    {
        // ...
    }

    /**
     * Event called when a task was executed but threw an exception.
     *
     * If an exception is thrown in this method, the worker will blow up!
     *
     * @codeCoverageIgnore
     *
     * @param Task      $task
     * @param Exception $e
     * @param bool $dispatcherNotified If true, then the dispatcher of the task was waiting for the task
     *                                 to execute and was notified that there was an error. If false, either
     *                                 the dispatcher wasn't waiting, either it stopped waiting after some time.
     */
    public function onTaskError(Task $task, Exception $e, $dispatcherNotified)
    {
        // ...
    }
}
```
