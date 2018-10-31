<?php

namespace Drupal\sunburst_api;

/**
 * A Drupal Service for connecting to the Sunburst API.
 *
 * API Docs: https://sunburst.sunsetwx.com/v1/docs.
 */
interface SunburstClientInterface {

  /**
   * Utilizes Drupal's httpClient to connect to the Sunburst API.
   *
   * API Docs: https://sunburst.sunsetwx.com/v1/docs/#introduction.
   *
   * @param string $method
   *   get, post, patch, delete, etc. See Guzzle documentation.
   * @param string $endpoint
   *   Sunburst API endpoint (ex. /quality)
   * @param array $options
   *   Guzzle options array.
   *
   * @return object
   *   \GuzzleHttp\Psr7\Response
   */
  public function connect($method, $endpoint, $options);

  /**
   * Get's the quality from the Sunburst API.
   *
   * API Docs: https://sunburst.sunsetwx.com/v1/docs/#get-quality.
   *
   * @param string $latLong
   *   A string that contains a commas seperated latitude and longitude point.
   *
   * @return object
   *   \GuzzleHttp\Psr7\Response body.
   */
  public function getQuality($latLong);

  /**
   * Initial login to get session credentials.
   *
   * API Docs: https://sunburst.sunsetwx.com/v1/docs/#post-login.
   *
   * @return array
   *   Array containing client_id and client_secret.
   */
  public function login();

  /**
   * Gets the session credentials stored in Drupal state.
   *
   * @return array
   *   Array containing client_id and client_secret.
   */
  public function getSessionTokens();

  /**
   * Generates an access token for the sunburst API.
   *
   * API Docs: https://sunburst.sunsetwx.com/v1/docs/#post-login-session.
   *
   * @return string
   *   Sunburst API access token.
   */
  public function generateAccessToken();

  /**
   * Generates an access token for the sunburst API.
   *
   * API Docs: https://sunburst.sunsetwx.com/v1/docs/#post-login-session.
   *
   * @param array $credentials
   *   Array containing client_id and client_secret.
   *
   * @return string
   *   Sunburst API access token used for other requests.
   */
  public function sessionLogin($credentials);

  /**
   * Gets the access token stored in Drupal state.
   *
   * @return string
   *   A string containing an access token.
   */
  public function getAccessToken();

  /**
   * Checks if an error has been returned from the API.
   *
   * @param object $contents
   *   JSON decoded body contents.
   *
   * @return bool
   *   TRUE if an error was found. FALSE if not.
   */
  public function hasError($contents);

  /**
   * Gets the response body from a Guzzle response.
   *
   * @param object $response
   *   \GuzzleHttp\Psr7\Response.
   *
   * @return object
   *   \GuzzleHttp\Psr7\Response body or json_decoded version.
   */
  public function getBody($response);

}
