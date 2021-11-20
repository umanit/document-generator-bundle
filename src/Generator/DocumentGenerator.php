<?php

namespace Umanit\DocumentGeneratorBundle\Generator;

use LogicException;
use Psr\Log\LoggerInterface;
use RuntimeException;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Umanit\DocumentGeneratorBundle\Exception\DocumentGeneratorException;

class DocumentGenerator
{
    private const AES_METHOD = 'aes-256-ctr';
    private const GET_PNG = 'png';
    private const GET_PDF = 'pdf';
    private const FROM_URL = 'url';
    private const FROM_HTML = 'html';

    private HttpClientInterface $client;
    private string $apiBaseUri;
    private bool $encryptData;
    private ?string $encryptionKey;
    private ?LoggerInterface $logger;

    public function __construct(
        HttpClientInterface $client,
        string $apiBaseUri,
        string $encryptionKey = null,
        LoggerInterface $logger = null
    ) {
        $this->client = $client;
        $this->apiBaseUri = rtrim($apiBaseUri, '/');
        $this->encryptionKey = $encryptionKey;
        $this->encryptData = false;
        $this->logger = $logger;
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
     * Generates a PNG from an URL.
     * Returns the binary string as a response.
     *
     * @param string $url     URL used to generate the document.
     * @param array  $options Options to give to the API.
     *
     * @return string
     * @throws DocumentGeneratorException
     */
    public function generatePngFromUrl(string $url, array $options = []): string
    {
        return $this->generate(self::GET_PNG, self::FROM_URL, $url, $options);
    }

    /**
     * Generates a PNG from a HTML string.
     * Returns the binary string as a response.
     *
     * @param string $html    HTML code used to generate the document.
     * @param array  $options Options to give to the API.
     *
     * @return string
     * @throws DocumentGeneratorException
     */
    public function generatePngFromHtml(string $html, array $options = []): string
    {
        return $this->generate(self::GET_PNG, self::FROM_HTML, $html, $options);
    }

    /**
     * Generates a PDF from a URL.
     * Returns the binary string as a response.
     *
     * @param string $url     URL used to generate the document.
     * @param array  $options Options to give to the API.
     *
     * @return string
     * @throws DocumentGeneratorException
     */
    public function generatePdfFromUrl(string $url, array $options = []): string
    {
        return $this->generate(self::GET_PDF, self::FROM_URL, $url, $options);
    }

    /**
     * Generates a PDF from a HTML string.
     * Returns the binary string as a response.
     *
     * @param string $html    HTML code used to generate the document.
     * @param array  $options Options to give to the API.
     *
     * @return string
     * @throws DocumentGeneratorException
     */
    public function generatePdfFromHtml(string $html, array $options = []): string
    {
        return $this->generate(self::GET_PDF, self::FROM_HTML, $html, $options);
    }

    /**
     * Processes options passed to the generator.
     *
     * @param array $options
     *
     * @return array
     */
    private function processOptions(array $options): array
    {
        $resolver = new OptionsResolver();

        $resolver
            ->setDefaults([
                'decode'      => false,
                'pageOptions' => [],
                'scenario'    => null,
            ])
            ->setAllowedTypes('decode', 'bool')
            ->setAllowedTypes('pageOptions', 'array')
            ->setAllowedTypes('scenario', ['null', 'string'])
        ;

        return $resolver->resolve($options);
    }

    /**
     * Calls the API and returns the binary result.
     *
     * @param string $type           Type of document to generate.
     * @param string $urlOrHtmlKey   "url" or "html" depending on the source of the document to generate.
     * @param string $urlOrHtmlValue Source of the document to generate.
     * @param array  $options        Options to give to the API.
     *
     * @return string
     * @throws DocumentGeneratorException if something went wrong.
     */
    private function generate(string $type, string $urlOrHtmlKey, string $urlOrHtmlValue, array $options = []): string
    {
        try {
            $options = $this->processOptions($options);
            $contentType = 'application/json';
            $endpoint = '/';
            $message = json_encode(array_merge($options, [
                'type'        => $type,
                $urlOrHtmlKey => $urlOrHtmlValue,
            ]), JSON_THROW_ON_ERROR);

            if ($this->encryptData) {
                $contentType = 'text/plain';
                $endpoint = '/encrypted';
                $message = $this->encrypt($message);
            }

            $response = $this->client->request('POST', $this->getFullUrl($endpoint), [
                'headers' => ['Content-Type' => $contentType],
                'body'    => $message,
            ]);

            if (200 !== $response->getStatusCode()) {
                throw new DocumentGeneratorException('Invalid response code.');
            }

            return $response->getContent();
        } catch (\Throwable $e) {
            $this->logException($e);

            throw new DocumentGeneratorException($e->getMessage(), $e->getCode(), $e);
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
        } catch (\Throwable $e) {
            throw new RuntimeException('Can not generate IV.');
        }

        $ciphertext = openssl_encrypt(
            $message,
            self::AES_METHOD,
            $this->encryptionKey,
            OPENSSL_RAW_DATA,
            $iv
        );
        $ciphertextHex = bin2hex($ciphertext);
        $ivHex = bin2hex($iv);

        return "$ivHex:$ciphertextHex";
    }

    /**
     * Log the exception if the logger is defined.
     *
     * @param \Throwable $e
     */
    private function logException(\Throwable $e): void
    {
        if (null === $this->logger) {
            return;
        }

        $this->logger->error($e->getMessage());
    }

    /**
     * Get the full URL to call.
     *
     * @param string $url
     *
     * @return string
     */
    private function getFullUrl(string $url): string
    {
        return $this->apiBaseUri.'/'.ltrim($url, '/');
    }
}
