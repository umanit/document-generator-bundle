<?php

namespace Umanit\DocumentGeneratorBundle\Generator;

use LogicException;
use Psr\Log\LoggerInterface;
use RuntimeException;
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Umanit\DocumentGeneratorBundle\Exception\DocumentGeneratorException;

/**
 * Class DocumentGenerator
 */
class DocumentGenerator
{
    /** @var string */
    private const AES_METHOD = 'aes-256-ctr';

    /** @var string */
    private const GET_PNG = 'png';

    /** @var string */
    private const GET_PDF = 'pdf';

    /** @var string */
    private const FROM_URL = 'url';

    /** @var string */
    private const FROM_HTML = 'html';

    /** @var HttpClientInterface */
    private $client;

    /** @var string */
    private $apiBaseUri;

    /** @var bool */
    private $encryptData;

    /** @var string|null */
    private $encryptionKey;

    /** @var LoggerInterface|null */
    private $logger;

    /**
     * DocumentGenerator constructor.
     *
     * @param string               $apiBaseUri    Base URI of the API.
     * @param string|null          $encryptionKey Key used to encrypt data if needed.
     * @param LoggerInterface|null $logger
     */
    public function __construct(string $apiBaseUri, string $encryptionKey = null, LoggerInterface $logger = null)
    {
        $this->apiBaseUri    = rtrim($apiBaseUri, '/');
        $this->encryptionKey = $encryptionKey;
        $this->encryptData   = false;
        $this->logger        = $logger;
    }

    /**
     * Indicates if data should be encrypted before send.
     *
     * @param bool $flag
     */
    public function encryptData(bool $flag): void
    {
        $this->encryptData = $flag;
    }

    /**
     * Gets the HttpClient used to call the API.
     *
     * @return HttpClientInterface
     */
    public function getClient(): HttpClientInterface
    {
        if (null === $this->client) {
            $this->client = $this->getDefaultClient();
        }

        return $this->client;
    }

    /**
     * Generates a PNG from an URL.
     * Returns the binary string as a response.
     *
     * @param string $url URL used to generate the document.
     *
     * @return string
     * @throws DocumentGeneratorException
     */
    public function generatePngFromUrl(string $url): string
    {
        return $this->process(self::GET_PNG, self::FROM_URL, $url);
    }

    /**
     * Generates a PNG from a HTML string.
     * Returns the binary string as a response.
     *
     * @param string $html   HTML code used to generate the document.
     * @param bool   $encode Indicates if the HTML code should be encoded before sending.
     *
     * @return string
     * @throws DocumentGeneratorException
     */
    public function generatePngFromHtml(string $html, bool $encode = false): string
    {
        if ($encode) {
            $html = $this->encodeHtml($html);
        }

        return $this->process(self::GET_PNG, self::FROM_HTML, $html);
    }

    /**
     * Generates a PDF from an URL.
     * Returns the binary string as a response.
     *
     * @param string $url URL used to generate the document.
     *
     * @return string
     * @throws DocumentGeneratorException
     */
    public function generatePdfFromUrl(string $url): string
    {
        return $this->process(self::GET_PDF, self::FROM_URL, $url);
    }

    /**
     * Generates a PDF from a HTML string.
     * Returns the binary string as a response.
     *
     * @param string $html   HTML code used to generate the document.
     * @param bool   $encode Indicates if the HTML code should be encoded before sending.
     *
     * @return string
     * @throws DocumentGeneratorException
     */
    public function generatePdfFromHtml(string $html, bool $encode = false): string
    {
        if ($encode) {
            $html = $this->encodeHtml($html);
        }

        return $this->process(self::GET_PDF, self::FROM_URL, $html);
    }

    /**
     * Encodes the HTML before send.
     *
     * @param string $html
     *
     * @return string
     */
    private function encodeHtml(string $html): string
    {
        return base64_encode($html);
    }

    /**
     * Calls the API and returns the binary result.
     *
     * @param string $type           Type of document to generate.
     * @param string $urlOrHtmlKey   "url" or "html" depending on the source of the document to generate.
     * @param string $urlOrHtmlValue Source of the document to generate.
     *
     * @return string
     * @throws DocumentGeneratorException if something went wrong.
     */
    private function process(string $type, string $urlOrHtmlKey, string $urlOrHtmlValue): string
    {
        try {
            $contentType = 'application/json';
            $endpoint    = '/';
            $message     = json_encode([
                'type'        => $type,
                $urlOrHtmlKey => $urlOrHtmlValue,
            ]);

            if ($this->encryptData) {
                $contentType = 'text/plain';
                $endpoint    = '/encrypted';
                $message     = $this->encrypt($message);
            }

            $client   = $this->getClient();
            $response = $client->request('POST', $endpoint, [
                'headers' => [
                    'Content-Type' => $contentType,
                ],
                'body'    => $message,
            ]);

            if (200 !== $response->getStatusCode()) {
                throw new DocumentGeneratorException('Invalid response code.');
            }

            return $response->getContent();
        } catch (\Exception $e) {
            $this->logException($e);

            throw new DocumentGeneratorException($e->getMessage(), $e->getCode());
        }
    }

    /**
     * Encrypt the whole message before send.
     * The used IV will be concatenated in front of the encrypted message.
     *
     * @param string $message
     *
     * @return string
     * @throws RuntimeException if OpenSSL is too old or the IV can not be generated.
     */
    private function encrypt(string $message): string
    {
        if (null === $this->encryptionKey) {
            throw new LogicException('Encryption key must be defined to encrypt data.');
        }

        // Check versions with Heartbleed vulnerabilities
        if (OPENSSL_VERSION_NUMBER <= 268443727) {
            throw new RuntimeException('OpenSSL Version too old.');
        }

        $ivSize = openssl_cipher_iv_length(self::AES_METHOD);

        try {
            $iv = random_bytes($ivSize);
        } catch (\Exception $e) {
            throw new RuntimeException('Can not generate IV.');
        }

        $ciphertext    = openssl_encrypt($message, self::AES_METHOD, $this->encryptionKey, OPENSSL_RAW_DATA, $iv);
        $ciphertextHex = bin2hex($ciphertext);
        $ivHex         = bin2hex($iv);

        return "$ivHex:$ciphertextHex";
    }

    /**
     * Instanciate the default HTTP client used to call the API.
     *
     * @return HttpClientInterface
     */
    private function getDefaultClient(): HttpClientInterface
    {
        $httpClient = HttpClient::create([
            'base_uri' => $this->apiBaseUri,
        ]);

        return $httpClient;
    }

    /**
     * Log the exception if the logger is defined.
     *
     * @param \Exception $e
     */
    private function logException(\Exception $e): void
    {
        if (!$this->logger) {
            return;
        }

        $this->logger->error($e->getMessage());
    }
}
