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
   * Sunburst Token.
   *
   * @var string
   */
  protected $token;

  /**
   * Sunburst Secret.
   *
   * @var string
   */
  protected $secret;

  /**
   * Sunburst Base URI.
   *
   * @var string
   */
  protected $base_uri;

  // \/ \/ \/ \/ from initial testing \/ \/ \/ \/
  $url = 'https://sunburst.sunsetwx.com/v1/login';

  /**
   * Constructor.
   */
  public function __construct(ClientInterface $http_client, KeyRepositoryInterface $key_repo, ConfigFactory $config_factory) {
    $this->httpClient = $http_client;
    $config = $config_factory->get('Sunburst_api.settings');
    $this->token = $config->get('token');
    $this->secret = $config->get('secret');
    $this->secret = $key_repo->getKey($this->secret)->getKeyValue();
    $this->base_uri = $config->get('base_uri');
  }

  // \/ \/ \/ \/ from initial testing \/ \/ \/ \/
  function sunburst_api_get_auth() {
    if (!$params = sunburst_api_login()) {
       return FALSE;
    }
  
    if (!$accessToken =  sunburst_api_session_login($params)) {
       return FALSE;
    }
    return $accessToken;

  /**
   * { @inheritdoc }
   */
  public function connect($method, $endpoint, $query, $body) {
    try {
      $response = $this->httpClient->{$method}(
        $this->base_uri . $endpoint,
        $this->buildOptions($query, $body)
      );
    }
    catch (RequestException $exception) {
      drupal_set_message(t('Failed to complete Sunburst Task "%error"', ['%error' => $exception->getMessage()]), 'error');

      \Drupal::logger('Sunburst_api')->error('Failed to complete Sunburst Task "%error"', ['%error' => $exception->getMessage()]);
      return FALSE;
    }

    $headers = $response->getHeaders();
    $this->throttle($headers);
    // TODO: Possibly allow returning the whole body.
    return $response->getBody()->getContents();
  }

  // \/ \/ \/ \/ from initial testing \/ \/ \/ \/
  function sunburst_api_login() {
    $options = [
      'auth' => [
        $username,
        $password,
      ],
      'form_params' => [
        'grant_type' => 'password',
        'type' => 'remember_me',
      ],
    ];
  
    $url = 'https://sunburst.sunsetwx.com/v1/login';

    try {
      $client = \Drupal::httpClient();
      $response = $client->post($url, $options);
      $contents = $response->getBody()->getContents();
      if (empty($contents)) {
        // Add log message.
        return FALSE;
      }
  
      $contents = json_decode($contents);
      if (empty($contents->session)) {
        return FALSE;
      }
      $params = [
        'client_id' => $contents->session->client_id,
        'client_secret' => $contents->session->client_secret,
      ];
      return $params;
  
    }
    catch (RequestException $e) {
      kint($e);
    }
  }

  
  }


  /**
   * Build options for the client.
   */
  private function buildOptions($query, $body) {
    $options = [];
    $options['auth'] = $this->auth();
    if ($body) {
      $options['body'] = $body;
    }
    if ($query) {
      $options['query'] = $query;
    }
    return $options;
  }

  /**
   * Throttle response.
   *
   * 100 per 60s allowed.
   */
  private function throttle($headers) {
    if ($headers['X-Sunburst-API-Request-Rate-Count'][0] > 99) {
      return sleep(60);
    }
    return TRUE;
  }

  /**
   * Handle authentication.
   */
  private function auth() {
    return [$this->token, $this->secret];
  }

  // \/ \/ \/ \/ from initial testing \/ \/ \/ \/
  function sunburst_api_session_login($params) {
    //curl -X "POST" "https://sunburst.sunsetwx.com/v1/login/session" -u "a3c5994b-58cf-4767-9b0a-2faf324da4ac:SeMzKSDtnO4WfN1uA1vVVGAYJB7FgMB3" -d "grant_type=client_credentials"Ȁ
      $options = [
        'auth' => [
          $params['client_id'],
          $params['client_secret'],
        ],
        'form_params' => [
          'grant_type' => 'client_credentials',
        ],
      ];
    
      $url = 'https://sunburst.sunsetwx.com/v1/login/session';
      try {
        $client = \Drupal::httpClient();
        $response = $client->post($url, $options);
        $contents = $response->getBody()->getContents();
        $contents = json_decode($contents);
        return $contents->access_token;
      }
      catch (RequestException $e) {
        kint($e);
      }
    }

  /**
   * Implements hook_help().
   */
  function sunburst_api_help($route_name, RouteMatchInterface $route_match) {
    switch ($route_name) {
      // Main module help for the sunburst_api module.
      case 'help.page.sunburst_api':
        $output = '';
        $output .= '<h3>' . t('About') . '</h3>';
        $output .= '<p>' . t('Handles API connection for https://sunburst.sunsetwx.com') . '</p>';
        return $output;
  
      default:
    }
  }

  // \/ \/ \/ \/ from initial testing \/ \/ \/ \/
  foreach ($detinations as $destination) {
    $data = sunburst_api_predictions($destination->latlon);
    $values = parse_values($data);
    $values->save();
  
  }
  
  // \/ \/ \/ \/ from initial testing \/ \/ \/ \/
  function sunburst_api_predictions($latLong) {
    $accessToken = sunburst_api_get_auth();
  //   dpm($accessToken);
  //   return;
    $options = [
      'headers' => [
        'Authorization' => 'Bearer ' . $accessToken,
      ],
      'query' => [
        'geo' => $latLong,
      ],
    ];
  
    $url = 'https://sunburst.sunsetwx.com/v1/quality';
    try {
      $client = \Drupal::httpClient();
      $response = $client->get($url, $options);
      dpm($response->getBody()->getContents());
    }
    catch (RequestException $e) {
      kint($e);
    }
  }

}
