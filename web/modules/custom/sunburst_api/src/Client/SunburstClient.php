<?php

namespace Drupal\Sunburst_api\Client;

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
  protected $base_uri;

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
    $this->base_uri = $config->get('base_uri');
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
  public function generateAccessToken() {
    if (!$params = $this->login()) {
      return FALSE;
    }

    if (!$accessToken = $this->sessionlogin($params)) {
      return FALSE;
    }

    // Set access token.
    \Drupal::state()->set('sunburst_api.access_token', $accessToken);
    return $accessToken;
  }

  /**
   * { @inheritdoc }
   */
  public getPrediction($latLong) {
    $accessToken = $this->getAccessToken();
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

    //TODO...FAILURE...RETRY with new accessToken.
  }

  /**
   * { @inheritdoc }
   */
  public function connect($method, $endpoint, $options) {
    try {
      $response = $this->httpClient->{$method}(
        $this->base_uri . $endpoint,
        $options
      );
    }
    catch (RequestException $exception) {
      drupal_set_message(t('Failed to complete Sunburst Task "%error"', ['%error' => $exception->getMessage()]), 'error');
      \Drupal::logger('Sunburst_api')->error('Failed to complete Sunburst Task "%error"', ['%error' => $exception->getMessage()]);
      return FALSE;
    }
    // $headers = $response->getHeaders();

    return $response;
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
    $params = [
      'client_id' => $contents->session->client_id,
      'client_secret' => $contents->session->client_secret,
    ];
    return $params;
  }

  /**
   * { @inheritdoc }
   */
  public function sessionLogin($params) {
    $options = [
      'auth' => [
        $params['client_id'],
        $params['client_secret'],
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

    if (empty($contents->access_token)) {
      return FALSE;
    }

    return $contents->access_token;
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
