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

    private Client $client;

    private \JsonMapper $jsonMapper;
    private string $email;
    private string $password;
    private string $token = '';
    public int $agreementId = 1;

    public string $language = 'en_US';
    public bool $withAddress = false;
    public array $deliveryAddress = [];
    public int $desiredShippingDays = 14;

    public function __construct($config = [])
    {
        $this->email = $config['email'];
        $this->password = $config['password'];
        $this->agreementId = $config['agreementId'];
        $this->language = $config['language'];

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
    private function updateToken(): void
    {
        if (\Yii::$app->cache->exists(self::class . '::token')) {
            $this->token = \Yii::$app->cache->get(self::class . '::token');
        } else {
            $this->auth();
        }
    }

    public function auth(): void
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
                    $tokenValidTo = \DateTimeImmutable::createFromFormat(
                        \DateTimeInterface::RFC3339_EXTENDED ,
                        $tokenResponse->validTo
                    );
                    $now = new \DateTimeImmutable('now', $tokenValidTo->getTimezone());
                    $duration = $tokenValidTo->format('U') - $now->format('U');

                    \Yii::$app->cache->set(self::class . '::token', $this->token, $duration);
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

    public function doRequest(string $method, string $url, array $options): ?ResponseInterface
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
    public function getAllowedAgreements(): array
    {
        $response = $this->doRequest('GET', self::ALLOWED_AGREEMENTS_URL, [
            'headers' => [
                'Accept' => 'application/json',
                'Authorization' => 'Bearer ' . $this->token
            ]
        ]);

        if ($response->getStatusCode() === self::HTTP_STATUS_SUCCESS) {
            $json = json_decode((string) $response->getBody());
            return $this->jsonMapper->mapArray($json, [], PartnerAgreement::class);
        }

        return [];
    }

    /**
     * @throws JsonMapper_Exception
     */
    public function viewCurrentOrder(): ?OrderLineListServiceResponse
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
     * @throws JsonMapper_Exception
     */
    private function addArticle(
        string $articleNumber,
        int $brandId,
        int $quantity,
        int $stockType,
        int $stockCode,
        int $deliveryCode
    ): ?BooleanServiceResponse
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
     * @throws JsonMapper_Exception
     */
    private function removeArticle(
        string $articleNumber,
        int $brandId,
        int $quantity,
        int $stockType,
        int $stockCode,
        int $deliveryCode
    ): ?BooleanServiceResponse
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
     * @throws JsonMapper_Exception
     */
    private function sendOrder(string $incomingNumber, string $note): ?BooleanServiceResponse
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
     * @throws JsonMapper_Exception
     */
    private function sendOrderWithAddress(
        string $incomingNumber,
        string $note,
        int $deliveryId,
        string $desiredShippingDate
    ): ?BooleanServiceResponse
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
     * @throws JsonMapper_Exception
     */
    private function searchArticle(
        string $articleId,
        string $brand
    ): ?OfferListServiceResponse
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
     * @throws JsonMapper_Exception
     */
    public function createOrder(
        $incomingNumber,
        $note,
        array $toOrder
    ): ?array
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
                } elseif ($original->availability < $part['quantity']) {
                    $part['error'] = self::ERROR_QUANTITY . " ({$original->availability})";
                    $part['ordered'] = 0;
                } else {
                    $response = $this->addArticle(
                        $part['oem'],
                        $part['brand'],
                        $part['quantity'],
                        $original->stockType,
                        $original->stockCode,
                        $original->deliveryCode
                    );

                    if (!$response) {
                        $part['ordered'] = 0;
                        $part['error'] = self::ERROR_ADD_ARTICLE;

                        self::trigger(self::EVENT_ADD_ARTICLE_FAIL);
                    } elseif ($response->success) {
                        $part['ordered'] = $part['quantity'];
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
            $desiredShippingDate = new \DateTimeImmutable('now');
            $desiredShippingDate->add(new \DateInterval("P{$this->desiredShippingDays}D"));

            $orderResult = $this->sendOrderWithAddress(
                $incomingNumber,
                $note,
                rand(0,99999), // TODO: ???
                $desiredShippingDate->format(\DateTimeInterface::RFC3339_EXTENDED)
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