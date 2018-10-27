<?php

namespace Drupal\sunburst_api\Client;

use Drupal\Core\Config\ConfigFactory;
use Drupal\key\KeyRepositoryInterface;
use Drupal\Sunburst_api\SunburstClientInterface;
use \GuzzleHttp\ClientInterface;
use \GuzzleHttp\Exception\RequestException;
use Drupal\Core\Routing\RouteMatchInterface;

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
   * Constructor.
   */
  public function __construct(ClientInterface $http_client, KeyRepositoryInterface $key_repo, ConfigFactory $config_factory) {
    $this->httpClient = $http_client;
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
    try {
      $response = $this->httpClient->{$method}(
        $this->baseUri . $endpoint,
        $options
      );
    }
    catch (RequestException $exception) {
      drupal_set_message(t('Failed to complete Sunburst Task "%error"', ['%error' => $exception->getMessage()]), 'error');
      \Drupal::logger('Sunburst_api')->error('Failed to complete Sunburst Task "%error"', ['%error' => $exception->getMessage()]);
      return FALSE;
    }

    return $response;
  }

  /**
   * { @inheritdoc }
   */
  public function getPrediction($latLong, $accessToken = NULL) {
    if (empty($accessToken)) {
      $accessToken = $this->getAccessToken();
    }
    $options = [
      'headers' => [
        'Authorization' => 'Bearer ' . $accessToken,
      ],
      'query' => [
        'geo' => $latLong,
      ],
    ];

    if (!$response = $this->connect('get', '/v1/quality', $options)) {
      return FALSE;
    }

    if (!$contents = $this->getBody($response)) {
      // TODO: Log here.
      return FALSE;
    }

    // Check for Error Response.
    if ($this->hasError($contents)) {
      // Try with a new access token.
      if ($accessToken = $this->generateAccessToken()) {
        $this->getPrediction($latLong, $accessToken);
        return FALSE;
      }
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
      // TODO: Log here.
      return FALSE;
    }

    if (empty($contents->session)) {
      // TODO: Log here.
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
    if (!$accessToken = $this->sessionlogin($credentials)) {
      // TODO: Logging?
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
      // TODO: Log here.
      return FALSE;
    }

    // Check for Error Response.
    if ($this->hasError($contents)) {
      // Need to re-login.
      if ($credentials = $this->login()) {
        $this->sessionLogin($credentials);
        return FALSE;
      }
    }

    if (empty($contents->access_token)) {
      // TODO: Log here.
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
  public function hasError($contents) {
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
  public function getBody($response, $decoded = TRUE) {
    $contents = $response->getBody()->getContents();
    if (empty($contents)) {
      return FALSE;
    }

    if ($decoded) {
      return json_decode($contents);
    }

    return $contents;
  }

}
