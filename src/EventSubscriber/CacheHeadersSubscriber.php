<?php

declare(strict_types=1);

namespace Drupal\libops\EventSubscriber;

use Drupal\Core\Cache\CacheableResponseInterface;
use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\ResponseEvent;

/**
 * Override headers to satisfy https://cloud.google.com/cdn/docs/caching#cacheability
 */
final class CacheHeadersSubscriber implements EventSubscriberInterface {

  /**
   * Constructs a new CacheHeadersSubscriber.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $configFactory
   *   The config factory.
   */
  public function __construct(
    protected ConfigFactoryInterface $configFactory,
  ) {
  }

  /**
   * Overrides cache control header for public responses.
   *
   * @param \Symfony\Component\HttpKernel\Event\ResponseEvent $event
   *   The event to process.
   */
  public function onRespond(ResponseEvent $event): void {
    if (FALSE === $event->isMainRequest()) {
      return;
    }

    $response = $event->getResponse();

    if (!$response instanceof CacheableResponseInterface) {
      return;
    }

    // don't extend the max age if this is not a public resource
    if (!$response->headers->hasCacheControlDirective('max-age') || !$response->headers->hasCacheControlDirective('public')) {
      return;
    }

    // don't let browsers cache HTML responses
    // cdn can cache responses for one day
    // cdn can serve stale cache for one day
    // since we're setting s-maxage, we don't need the public directive
    $response->headers->set('Cache-Control', 'max-age=0, s-maxage=86400, stale-while-revalidate=86400');
    // satisfy https://cloud.google.com/cdn/docs/caching#non-cacheable_content
    // make sure google cloud cdn adds Vary: cookie header in response
    if ($response->headers->get('Vary') == 'Cookie') {
      $response->headers->remove('Vary');
    }
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    return [
      KernelEvents::RESPONSE => [
        ['onRespond'],
      ],
    ];
  }

}
