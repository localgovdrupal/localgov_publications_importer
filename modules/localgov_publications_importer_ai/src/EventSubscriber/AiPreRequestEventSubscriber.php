<?php

namespace Drupal\localgov_publications_importer_ai\EventSubscriber;

use Drupal\ai\Event\PreGenerateResponseEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Subscribes to the Drupal\ai\Event\PreGenerateResponseEvent.
 */
class AiPreRequestEventSubscriber implements EventSubscriberInterface {

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    return [
      PreGenerateResponseEvent::EVENT_NAME => 'aiPreRequest',
    ];
  }

  /**
   * Alter the AI request configuration before it's sent.
   */
  public function aiPreRequest(PreGenerateResponseEvent $event): void {

    if ($event->getProviderId() === 'bedrock' && str_contains($event->getModelId(), 'anthropic.claude')) {
      // This is the maximum permitted token limit, and configuration option for
      // anthropic.claude-3-7-sonnet-20250219-v1:0 via AWS Bedrock.
      // @todo Handle other providers/models.
      $claude_max_tokens = 131071;

      // @todo Limit this to our own requests, somehow.
      $configuration = $event->getConfiguration();
      $configuration['maxTokens'] = $claude_max_tokens;
      $event->setConfiguration($configuration);
    }
  }

}
