<?php

namespace common\components\ATMSupplier;

use common\components\ATMSupplier\schemas\BooleanServiceResponse;
use common\components\ATMSupplier\schemas\OfferListServiceResponse;
use common\components\ATMSupplier\schemas\OrderLineListServiceResponse;
use common\components\ATMSupplier\schemas\PartnerAgreement;
use common\components\ATMSupplier\schemas\TokenResponse;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use JsonMapper_Exception;
use Psr\Http\Message\ResponseInterface;

class AMTSupplier extends \yii\base\Component
{
    const EVENT_UNAUTHORIZED = 'unauthorized';
    const EVENT_BAD_REQUEST = 'badRequest';
    const EVENT_ADD_ARTICLE_FAIL = 'addArticleFail';
    const EVENT_ORDER_SUCCESS = 'orderSuccess';
    const EVENT_ORDER_FAIL = 'orderFail';

    const HTTP_STATUS_SUCCESS = 200;

    const BASE_URL = 'https://api.handler.lv/api/';
    const AUTH_URL = 'Account/Login';
    const ALLOWED_AGREEMENTS_URL = 'Account/GetAllowedAgreements';
    const VIEW_CURRENT_ORDER_URL = 'Order/ViewCurrentOrder';
    const ADD_ARTICLE_TO_ORDER_URL = 'Order/AddArticleToOrder';
    const REMOVE_ARTICLE_FROM_ORDER_URL = 'Order/RemoveArticleFromOrder';
    const SEND_ORDER_URL = 'Order/SendOrder';
    const SEND_ORDER_WITH_ADDRESS_URL = 'Order/SendOrderWithAddress';
    const SEARCH_ARTICLE_URL = 'Parts/Search';

    const ERROR_PRICE = 'Too expensive';
    const ERROR_NOT_FOUND = 'Not found';
    const ERROR_ANALOGS = 'Analogs';
    const ERROR_QUANTITY = 'Quantity error';
    const ERROR_ADD_ARTICLE = 'Add article error';

    private $client;

    private $jsonMapper;

    private $email;
    private $password;
    private $token = '';
    public $agreementId = 1;

    public $language = 'en_US';
    public $withAddress = true;
    public $deliveryAddress = [];
    public $desiredShippingDays = 14;

    public function __construct($config = [])
    {
        $this->email = $config['email'];
        $this->password = $config['password'];

        $this->client = new Client([
            'base_uri' => self::BASE_URL,
            'connect_timeout' => 10.0
        ]);

        $this->jsonMapper = new \JsonMapper();

        $this->updateToken();

        unset($config['email']);
        unset($config['password']);

        parent::__construct($config);
    }

    /**
     * @return void
     */
    private function updateToken()
    {
        if (\Yii::$app->cache->exists(get_class($this) . '::token')) {
            $this->token = \Yii::$app->cache->get(get_class($this) . '::token');
        } else {
            $this->auth();
        }
    }

    /**
     * @return void
     */
    private function auth()
    {
        try {
            $response = $this->client->post(self::AUTH_URL, [
                'headers' => ['Accept' => 'application/json'],
                'query' => [
                    'email' => $this->email,
                    'password' => $this->password
                ]
            ]);

            if ($response->getStatusCode() === self::HTTP_STATUS_SUCCESS) {
                $json = json_decode((string) $response->getBody());

                /** @var TokenResponse $tokenResponse */
                $tokenResponse = $this->jsonMapper->map($json, new TokenResponse());

                if ($tokenResponse->token !== '') {
                    $this->token = $tokenResponse->token;
                    $tokenValidTo = \DateTime::createFromFormat(
                        \DateTime::RFC3339_EXTENDED ,
                        $tokenResponse->validTo
                    );
                    $now = new \DateTime('now', $tokenValidTo->getTimezone());
                    $duration = $tokenValidTo->format('U') - $now->format('U');

                    \Yii::$app->cache->set(get_class($this) . '::token', $this->token, $duration);
                } else {
                    self::trigger(self::EVENT_UNAUTHORIZED);
                }
            } else {
                self::trigger(self::EVENT_BAD_REQUEST);
            }
        } catch (GuzzleException $e) {
            self::trigger(self::EVENT_BAD_REQUEST);
        } catch (\Exception $e) {
        }
    }

    /**
     * @param $method
     * @param $url
     * @param $options
     * @return ResponseInterface|null
     */
    private function doRequest($method, $url, $options)
    {
        $i = 0;
        $statusCode = null;

        while ($statusCode !== self::HTTP_STATUS_SUCCESS && $i < 2) {
            try {
                $response = $this->client->request($method, $url, $options);
                $statusCode = $response->getStatusCode();
                if ($statusCode === self::HTTP_STATUS_SUCCESS) {
                    return $response;
                }
            } catch (GuzzleException $exception) {
                self::trigger(self::EVENT_BAD_REQUEST);
            }

            $this->auth();
            $i++;
        }

        return null;
    }

    /**
     * @throws JsonMapper_Exception
     *
     * @return PartnerAgreement[]
     */
    public function getAllowedAgreements()
    {
        $response = $this->doRequest('GET', self::ALLOWED_AGREEMENTS_URL, [
            'headers' => [
                'Accept' => 'application/json',
                'Authorization' => 'Bearer ' . $this->token
            ]
        ]);

        if ($response->getStatusCode() === self::HTTP_STATUS_SUCCESS) {
            $json = json_decode((string) $response->getBody());
            return $this->jsonMapper->mapArray($json, [], 'PartnerAgreement');
        }

        return [];
    }

    /**
     * @return mixed|object|string|null
     * @throws JsonMapper_Exception
     */
    public function viewCurrentOrder()
    {
        $response = $this->doRequest('GET', self::VIEW_CURRENT_ORDER_URL, [
            'headers' => [
                'Accept' => 'application/json',
                'Authorization' => 'Bearer ' . $this->token
            ],
            'query' => [
                'agreementId' => $this->agreementId,
                'language' => $this->language
            ]

        ]);

        if ($response->getStatusCode() === self::HTTP_STATUS_SUCCESS) {
            $json = json_decode((string) $response->getBody());
            return $this->jsonMapper->map($json, new OrderLineListServiceResponse());
        }

        return null;
    }

    /**
     * @param $articleNumber
     * @param $brandId
     * @param $quantity
     * @param $stockType
     * @param $stockCode
     * @param $deliveryCode
     * @return mixed|object|string|null
     * @throws JsonMapper_Exception
     */
    private function addArticle(
        $articleNumber,
        $brandId,
        $quantity,
        $stockType,
        $stockCode,
        $deliveryCode
    )
    {
        $response = $this->doRequest('POST', self::ADD_ARTICLE_TO_ORDER_URL, [
            'headers' => [
                'Accept' => 'application/json',
                'Authorization' => 'Bearer ' . $this->token
            ],
            'query' => [
                'articleNumber' => $articleNumber,
                'brandId' => $brandId,
                'stockType' => $stockType,
                'stockCode' => $stockCode,
                'deliveryCode' => $deliveryCode,
                'quantity' => $quantity,
                'agreementId' => $this->agreementId,
            ]
        ]);

        if ($response->getStatusCode() === self::HTTP_STATUS_SUCCESS) {
            $json = json_decode((string) $response->getBody());
            return $this->jsonMapper->map($json, new BooleanServiceResponse());
        }

        return null;
    }

    /**
     * @param $articleNumber
     * @param $brandId
     * @param $quantity
     * @param $stockType
     * @param $stockCode
     * @param $deliveryCode
     * @return mixed|object|string|null
     * @throws JsonMapper_Exception
     */
    private function removeArticle(
        $articleNumber,
        $brandId,
        $quantity,
        $stockType,
        $stockCode,
        $deliveryCode
    )
    {
        $response = $this->doRequest('DELETE', self::REMOVE_ARTICLE_FROM_ORDER_URL, [
            'headers' => [
                'Accept' => 'application/json',
                'Authorization' => 'Bearer ' . $this->token
            ],
            'query' => [
                'articleNumber' => $articleNumber,
                'brandId' => $brandId,
                'stockType' => $stockType,
                'stockCode' => $stockCode,
                'deliveryCode' => $deliveryCode,
                'quantity' => $quantity,
                'agreementId' => $this->agreementId,
            ]
        ]);

        if ($response->getStatusCode() === self::HTTP_STATUS_SUCCESS) {
            $json = json_decode((string) $response->getBody());
            return $this->jsonMapper->map($json, new BooleanServiceResponse());
        }

        return null;
    }

    /**
     * @param $incomingNumber
     * @param $note
     * @return mixed|object|string|null
     * @throws JsonMapper_Exception
     */
    private function sendOrder($incomingNumber, $note)
    {
        $response = $this->doRequest('POST', self::SEND_ORDER_URL, [
            'headers' => [
                'Accept' => 'application/json',
                'Authorization' => 'Bearer ' . $this->token
            ],
            'query' => [
                'agreementId' => $this->agreementId,
                'incomingNumber' => $incomingNumber,
                'note' => $note,
                'language' => $this->language,
            ]
        ]);

        if ($response->getStatusCode() === self::HTTP_STATUS_SUCCESS) {
            $json = json_decode((string) $response->getBody());
            return $this->jsonMapper->map($json, new BooleanServiceResponse());
        }

        return null;
    }

    /**
     * @param $incomingNumber
     * @param $note
     * @param $deliveryId
     * @param $desiredShippingDate
     * @return mixed|object|string|null
     * @throws JsonMapper_Exception
     */
    private function sendOrderWithAddress(
        $incomingNumber,
        $note,
        $deliveryId,
        $desiredShippingDate
    )
    {
        $response = $this->doRequest('POST', self::SEND_ORDER_WITH_ADDRESS_URL, [
            'headers' => [
                'Accept' => 'application/json',
                'Authorization' => 'Bearer ' . $this->token
            ],
            'query' => [
                'agreementId' => $this->agreementId,
                'incomingNumber' => $incomingNumber,
                'note' => $note,
                'language' => $this->language,
                'deliveryId' => $deliveryId,
                'desiredShippingDate' => $desiredShippingDate
            ],
            'json' => json_encode($this->deliveryAddress)
        ]);

        if ($response->getStatusCode() === self::HTTP_STATUS_SUCCESS) {
            $json = json_decode((string) $response->getBody());
            return $this->jsonMapper->map($json, new BooleanServiceResponse());
        }

        return null;
    }

    /**
     * @param $articleId
     * @param $brand
     * @return OfferListServiceResponse|null
     * @throws JsonMapper_Exception
     */
    private function searchArticle(
        $articleId,
        $brand
    )
    {
        $response = $this->doRequest('GET', self::SEARCH_ARTICLE_URL, [
            'headers' => [
                'Accept' => 'application/json',
                'Authorization' => 'Bearer ' . $this->token
            ],
            'query' => [
                'articleNumber' => $articleId,
                'brand' => $brand,
                'agreementId' => $this->agreementId,
                'language' => $this->language,
                'showAnalogs' => true
            ]
        ]);

        if ($response->getStatusCode() === self::HTTP_STATUS_SUCCESS) {
            $json = json_decode((string) $response->getBody());
            return $this->jsonMapper->map($json, new OfferListServiceResponse());
        }

        return null;
    }

    /**
     * @param $incomingNumber
     * @param $note
     * @param array $toOrder
     * @return array|null
     * @throws JsonMapper_Exception
     */
    public function createOrder(
        $incomingNumber,
        $note,
        array $toOrder
    )
    {
        $parts = $toOrder;

        foreach ($parts as &$part) {
            $offerList = $this->searchArticle($part['oem'], $part['brand'], true);

            if ($offerList->success) {
                $original = null;
                $analogs = [];

                foreach ($offerList->data as $offer) {
                    if ($offer->product->article === $part['oem']) {
                        $original = $offer;
                    } else {
                        $analogs[] = $offer;
                    }
                }

                if ($original === null) {
                    if (empty($analogs)) {
                        $part['error'] = self::ERROR_NOT_FOUND . " ({$original->price})";
                    } else {
                        $errorMsg = [];
                        foreach ($analogs as $analog) {
                            $errorMsg[] = "{$analog->product->article}[{$analog->price}]";
                        }
                        $errorMsg = implode(',', $errorMsg);

                        $part['error'] = self::ERROR_ANALOGS . " ({$errorMsg})";
                        $part['ordered'] = 0;
                    }
                } elseif ($original->price > $part['price']) {
                    $part['error'] = self::ERROR_PRICE . " ({$original->price})";
                    $part['ordered'] = 0;
                } else {
                    $ordered = $part['quantity'];
                    if ($original->availability < $part['quantity']) {
                        $part['error'] = self::ERROR_QUANTITY . " ({$original->availability})";
                        $ordered = $original->availability;
                    } else {
                        $part['error'] = null;
                    }

                    $response = $this->addArticle(
                        $part['oem'],
                        $part['brand'],
                        $ordered,
                        $original->stockType,
                        $original->stockCode,
                        $original->deliveryCode
                    );

                    if (!$response) {
                        $part['ordered'] = 0;
                        $part['error'] = self::ERROR_ADD_ARTICLE;

                        self::trigger(self::EVENT_ADD_ARTICLE_FAIL);
                    } elseif ($response->success) {
                        $part['ordered'] = $ordered;
                        $part['error'] = null;
                    } else {
                        $part['ordered'] = 0;
                        $part['error'] = $response->message;
                    }
                }
            }
        }

        if ($this->withAddress) {
            $orderResult = $this->sendOrder($incomingNumber, $note);
        } else {
            $desiredShippingDate = new \DateTime('now');
            $desiredShippingDate->add(new \DateInterval("P{$this->desiredShippingDays}D"));

            $orderResult = $this->sendOrderWithAddress(
                $incomingNumber,
                $note,
                rand(0,99999), // TODO: ???
                $desiredShippingDate->format(\DateTime::RFC3339_EXTENDED)
            );
        }


        if ($orderResult->success) {
            self::trigger(self::EVENT_ORDER_SUCCESS);
            return $parts;
        } else {
            self::trigger(self::EVENT_ORDER_FAIL);
            return null;
        }
    }
}