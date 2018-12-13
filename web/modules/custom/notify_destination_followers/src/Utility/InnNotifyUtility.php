<?php
namespace Drupal\notify\Utility;
use Drupal\Core\Queue\QueueFactory;
use Drupal\Core\Entity\EntityTypeManager;
use Drupal\node\NodeInterface;
use Drupal\Core\Mail\MailManager;
use Twilio\Rest\Client;
use Twilio\Exceptions\RestException;
use Drupal\key\KeyRepository;
use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\StringTranslation\TranslationInterface;
use Drupal\Core\Url;
/**
 * Class NotifyUtility.
 *
 * @package Drupal\notify
 */
class NotifyUtility {
  use StringTranslationTrait;
  private $debug = FALSE;
  //@Joel this sorting will happen sooner in the process for sunset right?
  //   private $validStatuses = ['PAID', 'CSM'];
  /**
   * Drupal\key\KeyRepository definition.
   *
   * @var \Drupal\key\KeyRepository
   */
  protected $keyRepository;
  /**
   * Drupal\Core\Entity\EntityTypeManager definition.
   *
   * @var \Drupal\Core\Entity\EntityTypeManager
   */
  protected $entityTypeManager;
  /**
   * The queue object.
   *
   * @var \Drupal\Core\Queue\QueueFactory
   */
  protected $queue;
  /**
   * Drupal\Core\Mail\MailManager definition.
   *
   * @var \Drupal\Core\Mail\MailManager
   */
  protected $pluginManagerMail;
  /**
   * Constructor.
   */
  public function __construct(KeyRepository $key_repository, EntityTypeManager $entity_type_manager, QueueFactory $queue, MailManager $plugin_manager_mail, TranslationInterface $string_translation) {
    $this->keyRepository = $key_repository;
    $this->entityTypeManager = $entity_type_manager;
    $this->queue = $queue;
    $this->pluginManagerMail = $plugin_manager_mail;
    $this->stringTranslation = $string_translation;
  }
  
  /**
   * { @inheritdoc }
   */
  public function checkTodaysBookings($type = NULL, $dates = 'start') {
    $storage = $this->entityTypeManager->getStorage('node');
    // TODO: DependencyInjection date.
    $now = new DrupalDateTime();
    $now->setTimezone(new \Datetimezone('MST'));
    $yesterday = new DrupalDateTime('-1 day');
    $yesterday->setTimezone(new \Datetimezone('MST'));
    // Starting today & valid status.
    $query = $storage->getQuery()
      ->accessCheck(FALSE)
      ->condition('type', 'booking')
      ->condition('status', 1)
      ->condition('field_checkfront_status', $this->validStatuses, 'IN');
    // Handle check-in vs check-out.
    if ($dates === 'start') {
      $query->condition('field_dates.value', $now->format('Y-m-d'));
    }
    elseif ($dates === 'end') {
      $query->condition('field_dates.end_value', $yesterday->format('Y-m-d'));
    }
    // Handle Email or SMS if passed in.
    if (!empty($type)) {
      $field = 'field_customer_' . $type;
      $query->exists($field);
    }
    $bookings = $query->execute();
    if (!$bookings) {
      return FALSE;
    }
    return $bookings;
  }
  /**
   * Current Active Bookings.
   */
  public function activeBookings() {
    $storage = $this->entityTypeManager->getStorage('node');
    $now = new DrupalDateTime();
    $yesterday = new DrupalDateTime('-1 day');
    $query = $storage->getQuery()
      ->accessCheck(FALSE)
      ->exists('field_room')
      ->condition('type', 'booking')
      ->condition('status', 1)
      ->condition('field_checkfront_status', $this->validStatuses, 'IN')
      ->condition('field_dates.value', $now->format('Y-m-d'), '<=')
      ->condition('field_dates.end_value', $yesterday->format('Y-m-d'), '>=');
    $bookings = $query->execute();
    if (!$bookings) {
      return FALSE;
    }
    return $bookings;
  }
  /**
   * { @inheritdoc }
   */
  public function queueNotifications($bookings, $type) {
    $queue = 'notify_' . $type . '_queue_worker';
    foreach ($bookings as $bookingId) {
      $allowed = FALSE;
      if ($this->isCourteousTime() && $this->bookingRoomReady($bookingId)) {
        $allowed = TRUE;
      }
      elseif ($this->isStandardCheckin()) {
        $allowed = TRUE;
      }
      // Notifications already exist.
      if ($this->hasNotifications($bookingId, $type)) {
        $allowed = FALSE;
      }
      if ($allowed) {
        // Add to the email or sms queue.
        $notification = new \stdClass();
        $notification->type = $type;
        $notification->bookingId = $bookingId;
        $this->queue->get($queue)->createItem($notification);
      }
    }
  }
  /**
   * { @inheritdoc }
   */
  public function isStandardCheckin() {
    $now = new DrupalDateTime();
    $now->setTimezone(new \Datetimezone('MST'));
    // Between 1PM and 8PM.
    if (($now->format('H') >= 13) && ($now->format('H') <= 21)) {
      return TRUE;
    }
    return FALSE;
  }
  /**
   * { @inheritdoc }
   */
  public function isCourteousTime() {
    // Between 10AM and 9PM.
    $now = new DrupalDateTime();
    $now->setTimezone(new \Datetimezone('MST'));
    if (($now->format('H') >= 10) && ($now->format('H') <= 21)) {
      return TRUE;
    }
    return FALSE;
  }
  /**
   * { @inheritdoc }
   */
  public function bookingRoomReady($bookingId) {
    $storage = $this->entityTypeManager->getStorage('node');
    $count = $storage->getQuery()
      ->condition('type', 'booking')
      ->condition('nid', $bookingId)
      ->condition('field_room_ready.value', 1)
      ->accessCheck(FALSE)
      ->count()
      ->execute();
    if ($count > 0) {
      return TRUE;
    }
    return FALSE;
  }
  /**
   * { @inheritdoc }
   */
  public function hasNotifications($bookingId, $type) {
    $storage = $this->entityTypeManager->getStorage('node');
    // Check if any notifications point to this booking.
    // Also checking for notification type.
    $result = $storage->getQuery()
      ->accessCheck(FALSE)
      ->condition('type', 'notification')
      ->condition('field_booking', $bookingId)
      ->condition('field_notification_type', $type);
    if ($type === 'sms') {
      // Don't count sms that fail to send.
      $smsError = ['failed', 'undelivered'];
      $result->condition('field_sms_delivery_status', $smsError, 'NOT IN');
    }
    $count = $result->count()->execute();
    if ($count > 0) {
      return TRUE;
    }
    return FALSE;
  }
  /**
   * { @inheritdoc }
   */
  public function sendEmail(NodeInterface $booking, $method = 'automatic') {
    $sendTo = $booking->field_customer_email->value;
    if ($this->debug) {
      \Drupal::logger('notify')->debug('This email notification was intended for @email (Booking: @booking)', ['@email' => $sendTo, '@booking' => $booking->id()]);
      // TODO: Put in a Key.
      $sendTo = 'joelsteidl@gmail.com';
    }
    $email = $this->pluginManagerMail->mail(
      'notify',
      'email_key',
      $sendTo,
      'en',
      ['booking' => $booking]
    );
    // Successful? Create notification node.
    if ($email['result']) {
      $details = [
        'type' => 'email',
        'method' => $method,
        'message' => 'Logging currently not available for emails.',
      ];
      $this->createNotification($booking, $details);
    }
  }
  /**
   * { @inheritdoc }
   */
  public function sendSms(NodeInterface $booking, $method = 'automatic') {
    $sendTo = $booking->field_customer_sms->value;
    if ($this->debug) {
      \Drupal::logger('notify')->debug('This SMS notification was intended for @phone (Booking: @booking)', ['@phone' => $sendTo, '@booking' => $booking->id()]);
      $sendTo = $this->keyRepository->getKey('dev_sms')->getKeyValue();
    }
    $replacements = [
      '@front_pin' => $this->keyRepository->getKey('front_pin')->getKeyValue(),
      '@room' => $booking->field_room->entity->getTitle(),
      '@pin' => $booking->field_room->entity->field_pin->value,
      '@email' => $booking->field_customer_email->value,
    ];
    // Default SMS that can be overriden on a room node.
    $smsMessage = "Hello! We are so happy to have you staying at The Dove Inn tonight! The code to the front door of the inn is @front_pin, followed by the lock key. You've reserved the @room, the code to your room is @pin, followed by the lock key. Please check your email, additional details were sent to @email. Enjoy your stay!";
    if (!$booking->field_room->entity->field_sms_message->isEmpty()) {
      $smsMessage = $booking->field_room->entity->field_sms_message->value;
    }
    $message = $this->t($smsMessage, $replacements);
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
      \Drupal::logger('notify')->error('SMS sending issue with Booking: @booking. @error status code.', ['@error' => $e->getStatusCode(), '@booking' => $booking->id()]);
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
   * Create notification node.
   */
  public function createNotification($booking, $details) {
    $storage = $this->entityTypeManager->getStorage('node');
    $values = $this->prepareNotificationNode($booking, $details);
    $node = $storage->create($values);
    $node->save();
  }
  /**
   * Prepare booking node values.
   */
  public function prepareNotificationNode($booking, $details) {
    // TODO: Add message!
    $title = $booking->getTitle() . ' ' . $details['type'] . ' notification';
    $values = [
      'type' => 'notification',
      'status' => 1,
      'title' => $title,
      'field_message' => $details['message'],
      'field_booking' => ['target_id' => $booking->id()],
      'field_notification_type' => $details['type'],
      'field_notification_method' => $details['method'],
    ];
    if (isset($details['sid'])) {
      $values['field_message_sid'] = $details['sid'];
    }
    return $values;
  }
  /**
   * Load booking node.
   */
  public function loadBooking($bookingId) {
    $storage = $this->entityTypeManager->getStorage('node');
    $booking = $storage->load($bookingId);
    if ($booking) {
      return $booking;
    }
    return FALSE;
  }
}