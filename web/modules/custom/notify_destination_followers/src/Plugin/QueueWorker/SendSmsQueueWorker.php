<?php

namespace Drupal\notify_destination_followers\Plugin\QueueWorker;

use Drupal\Core\Entity\EntityTypeManager;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Queue\QueueWorkerBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Get a SMS to each user for every destination they are subscribed to.
 *
 * @QueueWorker(
 *   id = "send_sms_queue_worker",
 *   title = @Translation("Send SMS Queue Worker"),
 *   cron = {"time" = 15}
 * )
 */
class SendSmsQueueWorker extends QueueWorkerBase implements ContainerFactoryPluginInterface {

  /**
   * Drupal\Core\Entity\EntityTypeManager definition.
   *
   * @var \Drupal\Core\Entity\EntityTypeManager
   */
  protected $entityTypeManager;

  protected $debug = FALSE;

  /**
   * Constructor.
   *
   * @param array $configuration
   * @param string $plugin_id
   * @param mixed $plugin_definition
   * @param \Drupal\Core\Entity\EntityTypeManager $entity_field_manager
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, EntityTypeManager $entity_type_manager) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * Implementation of the container interface to allow dependency injection.
   *
   * @param \Symfony\Component\DependencyInjection\ContainerInterface $container
   * @param array $configuration
   * @param string $plugin_id
   * @param mixed $plugin_definition
   *
   * @return static
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      empty($configuration) ? [] : $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('entity_type.manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function processItem($user_destination_pair) {
    $message = createMessage($user_destination_pair);
    //need to load user??
    $user_phone = $user_destination_pair->'uid'->field_mobile_phone;
    // die();
  }
  
  /**
   *  Create a message
   *
   * @param $data
   */
  public function createMessage($user_destination_pair) {
    $replacements = [
        //load destination ?? 
        '@destination_name' => $user_destination_pair->"destination_id"->name;
      ];
    $message = "The sunset at @destination_name is going to be great tonight! Don't miss it!";
    $message = $this->t($message,$replacements)
    return $message;
  }
  
  /**
   *  send a sms.
   */
  public function sendSms($user_destination_pair) {
    //load user??  
    $sendTo = $user_destination_pair->'uid'->field_mobile_phone->value;
    // TODO: needs to be updated.
    // if ($this->debug) {
    //   \Drupal::logger('notify')->debug('This SMS notification was intended for @phone (Booking: @booking)', ['@phone' => $sendTo, '@booking' => $booking->id()]);
    //   $sendTo = $this->keyRepository->getKey('dev_sms')->getKeyValue();
    // }

    // $replacements = [
    //   '@destination_name' => 
    // ];
    // // Default SMS that can be overriden for a destination.
    // $smsMessage = "The sunset at @destination_name is going to be great tonight";
    // if (!$booking->field_room->entity->field_sms_message->isEmpty()) {
    //   $smsMessage = $booking->field_room->entity->field_sms_message->value;
    // }
    // $message = $this->t($smsMessage, $replacements);

    // Your Account SID and Auth Token from twilio.com/console.
    $sid = $this->keyRepository->getKey('twilio_sid')->getKeyValue();
    $token = $this->keyRepository->getKey('twilio_token')->getKeyValue();
    $client = new Client($sid, $token);
    $phone = $this->keyRepository->getKey('twilio_phone')->getKeyValue();
    
    // Send SMS.
    // Capture response on non Prod.
    $params = [
      'from' => $phone,
      'body' => $message,
      'statusCallback' => $this->keyRepository->getKey('postb_url')->getKeyValue(),
    ];
    if (\Drupal::state()->get('doveinn.env') === 'production') {
      $token = $this->keyRepository->getKey('twilio_webhook_token')->getKeyValue();
      $params['statusCallback'] = Url::fromRoute('notify.twilio_webhook', ['token' => $token], ['absolute' => TRUE])->toString();
    }
    try {
      $sms = $client->messages->create($sendTo, $params);
    }
    catch (RestException $e) {
      \Drupal::logger('notify')->error('SMS sending issue with User: @user for Destination: @destination. @error status code.', ['@error' => $e->getStatusCode(), '@user' => $booking->id()]);
      return FALSE;
    }
    $details = [
      'type' => 'sms',
      'method' => $method,
      'message' => $message,
      'sid' => $sms->sid,
    ];
    $this->createNotification($booking, $details);
    return TRUE;
  }

  /**
   * Create log node.
   */
  public function createNotification($booking, $details) {
    $storage = $this->entityTypeManager->getStorage('node');
    $values = $this->prepareNotificationNode($booking, $details);
    $node = $storage->create($values);
    $node->save();
  }
}