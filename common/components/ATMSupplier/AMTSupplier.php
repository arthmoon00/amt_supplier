<?php declare(strict_types=1);

namespace common\components\ATMSupplier;

use common\components\ATMSupplier\schemas\BooleanServiceResponse;
use common\components\ATMSupplier\schemas\DeliveryAddress;
use common\components\ATMSupplier\schemas\OrderLineListServiceResponse;
use common\components\ATMSupplier\schemas\PartnerAgreement;
use common\components\ATMSupplier\schemas\TokenResponse;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\HandlerStack;
use JsonMapper_Exception;
use Psr\Http\Message\ResponseInterface;
use yii\base\Event;
use yii\web\Response;

use yii\db\Connection;

class AMTSupplier extends \yii\base\Component
{
    const EVENT_UNAUTHORIZED = 'unauthorized';
    const EVENT_BAD_REQUEST = 'badRequest';

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

    private Client $client;

    private \JsonMapper $jsonMapper;
    private string $email;
    private string $password;
    private string $token;
    public int $agreementId = 1;

    public string $language = 'EN';
    public int $stockType;
    public int $stockCode;
    public int $deliveryCode;

    public function __construct($config = [])
    {
        $this->email = $config['email'];
        $this->password = $config['password'];
        $this->agreementId = $config['agreementId'];
        $this->language = $config['language'];

        $this->client = new Client([
            'base_uri' => self::BASE_URL,
            'connect_timeout' => 10.0,
            'max_retry_attempts' => 2,
            'on_retry_callback' => function () {
                echo 123;
            }
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
    private function viewCurrentOrder(): ?OrderLineListServiceResponse
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
        int $quantity
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
                'stockType' => $this->stockType,
                'stockCode' => $this->stockCode,
                'deliveryCode' => $this->deliveryCode,
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
        int $quantity
    ): ?BooleanServiceResponse
    {
        $response = $this->doRequest('POST', self::REMOVE_ARTICLE_FROM_ORDER_URL, [
            'headers' => [
                'Accept' => 'application/json',
                'Authorization' => 'Bearer ' . $this->token
            ],
            'query' => [
                'articleNumber' => $articleNumber,
                'brandId' => $brandId,
                'stockType' => $this->stockType,
                'stockCode' => $this->stockCode,
                'deliveryCode' => $this->deliveryCode,
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
    private function sendOrder() {}
    private function sendOrderWithAddress() {}
    private function searchArticle() {}
    public function createOrder(): string
    {

    }
}