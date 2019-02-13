<?php
namespace Drupal\notify_destination_followers\Utility;

use Drupal\Core\Queue\QueueFactory;
use Drupal\Core\Entity\EntityTypeManager;
use Drupal\node\NodeInterface;
use Drupal\Core\Mail\MailManager;
use Twilio\Rest\Client;
use Twilio\Exceptions\RestException;
use Drupal\key\KeyRepository;
use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\Core\Url;

/**
 * Class NotificationUtility.
 * 
 *  @Joel is this important?
 * @package Drupal\notification
 */
class SmsUtility {
  private $debug = FALSE;
  //TODO: @Joel this sorting will happen sooner in the process for sunset right?
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
  public function __construct(KeyRepository $key_repository, EntityTypeManager $entity_type_manager) {
    $this->keyRepository = $key_repository;
    $this->entityTypeManager = $entity_type_manager;
  }

   /**
   *  send a sms.
   * 
   * @param int $uid
   *   A drupal user id.
   * 
   * @return mixed
   */
  public function sendSms($uid, $message) {

    //TODOS:
    // 2. Add logging
    // 1. Call this service from the correct queue worker.

    if (!$sendTo = $this->getUserPhone($uid)) {
      // TODO: Log here!
      return FALSE;
    }
    $userName = $uid->name;
    $sid = $this->keyRepository->getKey('twilio_sid')->getKeyValue();
    $token = $this->keyRepository->getKey('twilio_token')->getKeyValue();
    $client = new Client($sid, $token);
    $phone = $this->keyRepository->getKey('twilio_phone')->getKeyValue();
    $params = [
      'from' => $phone,
      'body' => $message,
      //@Joel not sure what url to use
      // 'statusCallback' => $this->keyRepository->getKey('postb_url')->getKeyValue(),
    ];
    //TODO: Sends SMS if in production
    //@Joel not sure what the 'dovneinn.env' equilivant is this 'sunset.test'
    // if (\Drupal::state()->get('doveinn.env') === 'production') {
    //   //@Joel what is the twilio_webhook_token 
    //   $token = $this->keyRepository->getKey('twilio_webhook_token')->getKeyValue();
    //   $params['statusCallback'] = Url::fromRoute('notify.twilio_webhook', ['token' => $token], ['absolute' => TRUE])->toString();
    // }
    try {
      $sms = $client->messages->create($sendTo, $params);
    }
    catch (RestException $e) {
      //@Joel need to match our log fromat??
      \Drupal::logger('notify')->error('SMS sending issue with User: @userName, @uid for Destination: @destination. @error status code.', ['@error' => $e->getStatusCode(), '@userName' => $userName, '@uid' => $uid]);
      return FALSE;
    }

    //TODO: logging
    if ($this->debug) {
      \Drupal::logger('notify')->debug('This SMS notification was intended for @phone (User: @userName)', ['@phone' => $sendTo, '@userName' => $userName]);
      $sendTo = $this->keyRepository->getKey('dev_sms')->getKeyValue();
    }

  }

  /**
   * Gets a users phone number.
   * 
   * @param int $uid
   *   A drupal user id.
   * 
   * @return mixed
   *   FALSE or a valid phone number.
   */
  public function getUserPhone($uid) {
    $storage = $this->entityTypeManager->getStorage('user');
    if (!$user = $storage->load($uid)) {
      return FALSE;
    }
    if ($user->field_mobile_phone->isEmpty()) {
      return FALSE;
    }
    return $user->field_mobile_phone->value;
  }

  /**
   * Create log node.
   * 
   * TODO: this is prob broken
   */
  public function createLog($parent, $details) {
    $storage = $this->entityTypeManager->getStorage('node');
    $values = $this->prepareNotificationNode($node, $details);
    $node = $storage->create($values);
    $node->save();
  }
 
 
  

  
}