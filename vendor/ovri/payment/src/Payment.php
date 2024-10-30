<?php

/**
 * Class OVRI Payment
 * v 1.0.1 - 2022-10-25
 * This class allows the initialization of a payment to retrieve the token needed to use OVRI libraries
 */

namespace ovri;

class Payment
{

  /**
   * Initialization of the minimum required identification parameters
   */
  public $beforesign;
  public function __construct($params)
  {
    $this->apiuri = $params['API'];
    $this->merchantkey = $params['MerchantKey'];
    $this->secretkey = $params['SecretKey'];
    $this->client = new \GuzzleHttp\Client();
  }
  /**
   * GET request to Ovri
   *
   * @param $url
   * @param bool $postParameters
   * @return mixed|\Psr\Http\Message\ResponseInterface
   * @throws \GuzzleHttp\Exception\GuzzleException
   * And auto convert to json and decoding for final result and simplifie to rest on class
   */
  private function getRequest($url, $postParameters = false)
  {
    try {
      $postRequest = $this->client->request('GET', $url, ['query' => $postParameters]);
    } catch (\GuzzleHttp\Exception\RequestException $e) {
      $postRequest = $e->getResponse();
    }
    if ($postRequest->getBody() && $postRequest->getStatusCode()) {
      $returnArr = array();
      $responsesObjet = json_decode((string) $postRequest->getBody());
      foreach ($responsesObjet as $key => $value) {
        $returnArr[$key] = $value;
      }
      $returnArr['http'] = $postRequest->getStatusCode();
      return $returnArr;
    }
    return $returnArr;
  }

  /**
   * Do a urlencoded form POST
   *
   * @param $url
   * @param bool $postParameters
   * @return mixed|\Psr\Http\Message\ResponseInterface
   * @throws \GuzzleHttp\Exception\GuzzleException
   * And auto convert to json and decoding for final result and simplifie to rest on class
   *  
   */
  private function postForm($url, $postParameters = false)
  {
    try {
      $postRequest = $this->client->request('POST', $url, [
        'headers' => [
          'Authorization' => "$this->merchantkey|$this->secretkey"
        ],
        'form_params' => $postParameters
      ]);
    } catch (\GuzzleHttp\Exception\RequestException $e) {
      $postRequest = $e->getResponse();
    }
    if ($postRequest->getBody() && $postRequest->getStatusCode()) {
      $returnArr = array();
      $responsesObjet = json_decode((string) $postRequest->getBody());
      foreach ($responsesObjet as $key => $value) {
        $returnArr[$key] = $value;
      }
      $returnArr['http'] = $postRequest->getStatusCode();
      return $returnArr;
    }
    return $returnArr;
  }

  private function GenerateSignature(array $jsondata)
  {
    $stringSign = base64_encode(hash('sha512', json_encode($jsondata) . $this->secretkey));
    return $this->merchantkey . '.' . $stringSign;
  }


  private function postFormJson($url, $postParameters = false)
  {
    try {
      $postRequest = $this->client->request('POST', $url, [
        'headers' => [
          'Authorization' => "Bearer " . $this->GenerateSignature($postParameters)
        ],
        'body' => json_encode($postParameters)
      ]);
    } catch (\GuzzleHttp\Exception\RequestException $e) {
      $postRequest = $e->getResponse();
    }

    if ($postRequest->getBody() && $postRequest->getStatusCode()) {
      $returnArr = array();
      $responsesObjet = json_decode((string) $postRequest->getBody());
      foreach ($responsesObjet as $key => $value) {
        $returnArr[$key] = $value;
      }
      $returnArr['http'] = $postRequest->getStatusCode();
      return $returnArr;
    }
    return $returnArr;
  }

  /**
   * Generation of the SHA security key
   * Encryption of information in order to anonymize parameters for the frontend user
   * @param return $data with SHA encryption
   */
  private function signRequest($data, $beforesign = "")
  {
    foreach ($data as $key => $value) {
      $beforesign .= $value . "!";
    }
    $beforesign .= $this->secretkey;

    $sign = hash("sha512", base64_encode($beforesign . "|" . $this->secretkey));
    $data['SHA'] = hash("sha512", base64_encode($beforesign . "|" . $this->secretkey));
    return $data;
  }
  /**
   * Main function for initiating a token to start a web or iframe payment or via Ovri libraries.
   * Public @
   */
  public function initializePayment($body): array
  {
    $body['MerchantKey'] = $this->merchantkey;
    $PostVars = $this->signRequest($body);
    $responses = $this->postForm($this->apiuri . "/payment/init_transactions/", $PostVars);
    if ($responses['Code'] === "200") {
      return $responses;

    } else {
      return $responses;
    }
  }
  /**
   *Function to know the status of a payment according to your own reference @RefOrder
   */
  public function getStatusPayment($body)
  {
    $bodytosign['ApiKey'] = $this->merchantkey;
    $bodytosign['MerchantOrderId'] = $body['MerchantOrderId'];
    $signed = $this->signRequest($bodytosign);
    $responses = $this->getRequest($this->apiuri . "/payment/transactions_by_merchantid/", $signed);
    return $responses;
  }

  public function directAuthorization($body)
  {
    $responses = $this->postFormJson($this->apiuri . "/payment/authorization", $body);
    return $responses;
  }
}