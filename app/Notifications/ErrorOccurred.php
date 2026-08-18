<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;
use Illuminate\Notifications\Slack\SlackMessage;

class ErrorOccurred extends Notification implements ShouldQueue
{
    use Queueable;

    protected string $exceptionMessage;
    protected string $exceptionFile;
    protected int $exceptionLine;
    protected string $exceptionClass;

    public function __construct(\Throwable $exception)
    {
        $this->exceptionMessage = $exception->getMessage();
        $this->exceptionFile = $exception->getFile();
        $this->exceptionLine = $exception->getLine();
        $this->exceptionClass = get_class($exception);
    }

    public function via(object $notifiable): array
    {
        return ['slack'];
    }

    public function toSlack($notifiable): SlackMessage
    {
        return (new SlackMessage)
            ->text('🚨 An error occurred')
            ->headerBlock('Error Alert')
            ->sectionBlock(function ($block) {
                $block->text('*Message:* ' . $this->exceptionMessage);
            })
            ->contextBlock(function ($block) {
                $block->text(
                    $this->exceptionClass . ' at ' . $this->exceptionFile . ':' . $this->exceptionLine
                );
            });
    }

    public function toArray(object $notifiable): array
    {
        return [];
    }
}