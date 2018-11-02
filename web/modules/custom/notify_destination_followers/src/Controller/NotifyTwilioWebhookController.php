<?php

namespace Drupal\notify\Controller;

use Drupal\Core\Controller\ControllerBase;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\key\KeyRepository;
use Drupal\Core\Access\AccessResult;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;

/**
 * Class NotifyTwilioWebhookController.
 */
class NotifyTwilioWebhookController extends ControllerBase {

  /**
   * Drupal\key\KeyRepository definition.
   *
   * @var \Drupal\key\KeyRepository
   */
  protected $keyRepository;
  /**
   * Drupal\Core\Entity\EntityTypeManagerInterface definition.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;
  /**
   * Drupal\Core\Logger\LoggerChannelFactoryInterface definition.
   *
   * @var \Drupal\Core\Logger\LoggerChannelFactoryInterface
   */
  protected $logger;

  /**
   * Constructs a new NotifyTwilioWebhookController object.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager, LoggerChannelFactoryInterface $logger, KeyRepository $key_repository) {
    $this->entityTypeManager = $entity_type_manager;
    $this->logger = $logger->get('notify');
    $this->keyRepository = $key_repository;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('logger.factory'),
      $container->get('key.repository')
    );
  }

/**
   * Capture.
   *
   * @return string
   *   Return Hello string.
   */
  public function capture(Request $request) {
    // Return a simple page for increased load time.
    $response = new Response();

    // Capture the payload.
    $payload = $request->getContent();
    if (!empty($payload)) {
      if ($this->process($payload)) {
        $response->setContent('<h1>Twilio response processed.</h1>');
        return $response;
      }
    }

    // Failed.
    $this->logger->debug('The Twilio webhook passed empty data or payload could not be processed.');
    $response->setContent('<h1>Twilio webhook passed empty data.</h1>');
    return $response;
  }

  /**
   * Query by SID and save status.
   *
   * @param string $payload
   *   Paylod sent through from Twilio.
   *
   * @return bool
   *   TRUE or FALSE.
   */
  public function process($payload) {
    parse_str($payload, $params);
    if (empty($params['SmsSid']) || empty($params['SmsStatus'])) {
      return FALSE;
    }
    $sid = $params['SmsSid'];
    $status = $params['SmsStatus'];
    $storage = $this->entityTypeManager->getStorage('node');
    $result = $storage->getQuery()
      ->accessCheck(FALSE)
      ->condition('type', 'notification')
      ->condition('field_message_sid', $sid)
      ->execute();

    if (empty($result)) {
      return FALSE;
    }

    // Just get one.
    $nid = reset($result);
    if (!$notification = $storage->load($nid)) {
      return FALSE;
    }
    $smsError = ['failed', 'undelivered'];
    if (in_array($status, $smsError)) {
      $this->logger->notice('SMS failed to send for notification @nid.', ['@nid' => $nid]);
    }
    $notification->set('field_sms_delivery_status', $status)->save();
    return TRUE;
  }

  /**
   * Simple authorization using a token.
   *
   * @return AccessResult
   *   allowed or forbidden.
   */
  public function authorize($token) {
    $storedToken = $this->keyRepository->getKey('twilio_webhook_token')->getKeyValue();
    if ($token === $storedToken) {
      return AccessResult::allowed();
    }
    return AccessResult::forbidden();
  }
}
