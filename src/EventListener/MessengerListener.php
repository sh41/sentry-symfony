<?php

namespace Sentry\SentryBundle\EventListener;

use Sentry\FlushableClientInterface;
use Symfony\Component\Messenger\Event\WorkerMessageFailedEvent;
use Symfony\Component\Messenger\Event\WorkerMessageHandledEvent;
use Symfony\Component\Messenger\Exception\HandlerFailedException;

final class MessengerListener
{
    /**
     * @var FlushableClientInterface
     */
    private $client;

    /**
     * @var bool
     */
    private $captureSoftFails;

    /**
     * @param FlushableClientInterface $client
     * @param bool                     $captureSoftFails
     */
    public function __construct(FlushableClientInterface $client, bool $captureSoftFails = true)
    {
        $this->client = $client;
        $this->captureSoftFails = $captureSoftFails;
    }

    /**
     * @param WorkerMessageFailedEvent $event
     */
    public function onWorkerMessageFailed(WorkerMessageFailedEvent $event): void
    {
        if (! $this->captureSoftFails && $event->willRetry()) {
            // Don't capture soft fails. I.e. those that will be scheduled for retry.
            return;
        }

        $error = $event->getThrowable();

        if ($error instanceof HandlerFailedException && null !== $error->getPrevious()) {
            // Unwrap the messenger exception to get the original error
            $error = $error->getPrevious();
        }

        $this->client->captureException($error);
        $this->client->flush();
    }

    /**
     * @param WorkerMessageHandledEvent $event
     */
    public function onWorkerMessageHandled(WorkerMessageHandledEvent $event): void
    {
        // Flush normally happens at shutdown... which only happens in the worker if it is run with a lifecycle limit
        // such as --time=X or --limit=Y. Flush immediately in a background worker.
        $this->client->flush();
    }
}
