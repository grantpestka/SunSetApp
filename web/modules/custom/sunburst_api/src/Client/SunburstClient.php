<?php

namespace Drupal\sunburst_api\Client;

use Drupal\Core\Config\ConfigFactory;
use Drupal\key\KeyRepositoryInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\sunburst_api\SunburstClientInterface;
use \GuzzleHttp\ClientInterface;
use \GuzzleHttp\Exception\RequestException;

class SunburstClient implements SunburstClientInterface {

  /**
   * An http client.
   *
   * @var \GuzzleHttp\ClientInterface
   */
  protected $httpClient;

  /**
   * A configuration instance.
   *
   * @var \Drupal\Core\Config\ConfigInterface;
   */
  protected $config;

  /**
   * Sunburst Username.
   *
   * @var string
   */
  protected $username;

  /**
   * Sunburst Password.
   *
   * @var string
   */
  protected $password;

  /**
   * Sunburst Base URI.
   *
   * @var string
   */
  protected $baseUri;

  /**
   * Drupal\Core\Logger\LoggerChannelFactoryInterface definition.
   *
   * @var \Drupal\Core\Logger\LoggerChannelFactoryInterface
   */
  protected $logger;

  /**
   * Constructor.
   */
  public function __construct(ClientInterface $http_client, KeyRepositoryInterface $key_repo, LoggerChannelFactoryInterface $logger_factory, ConfigFactory $config_factory) {
    $this->httpClient = $http_client;
    $this->logger = $logger_factory->get('logger.factory');
    $config = $config_factory->get('sunburst_api.settings');
    $this->username = $config->get('username');
    $this->username = $key_repo->getKey($this->username)->getKeyValue();
    $this->password = $config->get('password');
    $this->password = $key_repo->getKey($this->password)->getKeyValue();
    $this->baseUri = $config->get('base_uri');
  }

  /**
   * { @inheritdoc }
   */
  public function connect($method, $endpoint, $options) {
    $error = FALSE;
    try {
      $response = $this->httpClient->{$method}(
        $this->baseUri . $endpoint,
        $options
      );
    }
    catch (RequestException $exception) {
      $this->logger->notice('Failed to complete Sunburst Task "%error"', ['%error' => $exception->getMessage()]);

      if (!$this->hasError($exception)) {
        return FALSE;
      }
      $error = TRUE;
    }

    // Check for Error Response.
    // TODO: Add counter to prevent loop.
    if ($error) {
      if (isset($options['headers']['Authorization'])) {
        $options['headers']['Authorization'] = 'Bearer ' . $this->generateAccessToken();
        return $this->connect($method, $endpoint, $options);
      }
      elseif (isset($options['auth'])) {
        if (!$credentials = $this->login()) {
          return FALSE;
        }
        $options['auth'] = [
          $credentials['client_id'],
          $credentials['client_secret'],
        ];
        return $this->connect($method, $endpoint, $options);
      }
      return FALSE;
    }

    return $response;
  }

  /**
   * { @inheritdoc }
   */
  public function getQuality($geo, $params = array()) {
    $options = [
      'headers' => [
        'Authorization' => 'Bearer ' . $this->getAccessToken(),
      ],
      'query' => [
        'geo' => $geo,
      ],
    ];

    // Add any additonal params passed through.
    if (!empty($params)) {
      foreach ($params as $paramKey => $paramValue) {
        $options['query'][$paramKey] = $paramValue;
      }
    }

    if (!$response = $this->connect('get', '/v1/quality', $options)) {
      return FALSE;
    }

    if (!$contents = $this->getBody($response, TRUE)) {
      $this->logger->notice('Get Quality could not be fetched for @geo', ['@geo' => $geo]);
      return FALSE;
    }

    return $contents;
  }

  /**
   * { @inheritdoc }
   */
  public function login() {
    $options = [
      'auth' => [
        $this->username,
        $this->password,
      ],
      'form_params' => [
        'grant_type' => 'password',
        'type' => 'remember_me',
      ],
    ];

    if (!$response = $this->connect('post', '/v1/login', $options)) {
      return FALSE;
    }

    if (!$contents = $this->getBody($response)) {
      $this->logger->notice('Failed to login to the suburst API.');
      return FALSE;
    }

    if (empty($contents->session)) {
      $this->logger->notice('Suburst login successful but missing session.');
      return FALSE;
    }

    $credentials = [
      'client_id' => $contents->session->client_id,
      'client_secret' => $contents->session->client_secret,
    ];
    foreach ($credentials as $key => $credential) {
      \Drupal::state()->set('sunburst_api.' . $key, $credential);
    }

    return $credentials;
  }

  /**
   * { @inheritdoc }
   */
  public function getSessionTokens() {
    $keys = [
      'client_id',
      'client_secret',
    ];
    $credentials = [];
    foreach ($keys as $key) {
      $credentials[$key] = \Drupal::state()->get('sunburst_api.' . $key);
    }
    return $credentials;
  }

  /**
   * { @inheritdoc }
   */
  public function generateAccessToken() {
    $credentials = $this->getSessionTokens();
    if (!$accessToken = $this->sessionLogin($credentials)) {
      $this->logger->notice('An access token could not be returned from the sunburst api.');
      return FALSE;
    }

    // Set access token.
    \Drupal::state()->set('sunburst_api.access_token', $accessToken);
    return $accessToken;
  }

  /**
   * { @inheritdoc }
   */
  public function sessionLogin($credentials) {
    $options = [
      'auth' => [
        $credentials['client_id'],
        $credentials['client_secret'],
      ],
      'form_params' => [
        'grant_type' => 'client_credentials',
      ],
    ];

    if (!$response = $this->connect('post', '/v1/login/session', $options)) {
      return FALSE;
    }

    if (!$contents = $this->getBody($response)) {
      $this->logger->notice('Session Login failed.');
      return FALSE;
    }

    if (empty($contents->access_token)) {
      $this->logger->notice('An access token was not returned from the sunburst api.');
      return FALSE;
    }

    return $contents->access_token;
  }

  /**
   * { @inheritdoc }
   */
  public function getAccessToken() {
    // TODO: Change to dependency injection.
    $accessToken = \Drupal::state()->get('sunburst_api.access_token');
    return $accessToken;
  }

  /**
   * { @inheritdoc }
   */
  public function hasError($exception) {
    $contents = json_decode($exception->getResponse()->getBody());
    if (!isset($contents->error)) {
      return FALSE;
    }

    if ($contents->error === 'invalid_grant') {
      return TRUE;
    }
    elseif ($contents->error === 'Unauthorized: invalid token') {
      return TRUE;
    }
    return FALSE;
  }

  /**
   * { @inheritdoc }
   */
  public function getBody($response) {
    $contents = $response->getBody()->getContents();
    if (empty($contents)) {
      return FALSE;
    }

    return json_decode($contents);
  }

}
