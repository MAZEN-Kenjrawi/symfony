<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Mime\Crypto;

use Symfony\Component\Mime\Exception\RuntimeException;
use Symfony\Component\Mime\Message;

/**
 * @author Sebastiaan Stok <s.stok@rollerscapes.net>
 */
final class SMimeSigner extends SMime
{
    private $signCertificate;
    private $signPrivateKey;
    private $signOptions;
    private $extraCerts;

    /**
     * @var string|null
     */
    private $privateKeyPassphrase;

    /**
     * @see https://secure.php.net/manual/en/openssl.pkcs7.flags.php
     *
     * @param string      $certificate
     * @param string      $privateKey           A file containing the private key (in PEM format)
     * @param string|null $privateKeyPassphrase A passphrase of the private key (if any)
     * @param string      $extraCerts           A file containing intermediate certificates (in PEM format) needed by the signing certificate
     * @param int         $signOptions          Bitwise operator options for openssl_pkcs7_sign()
     */
    public function __construct(string $certificate, string $privateKey, ?string $privateKeyPassphrase = null, ?string $extraCerts = null, int $signOptions = PKCS7_DETACHED)
    {
        $this->signCertificate = $this->normalizeFilePath($certificate);

        if (null !== $privateKeyPassphrase) {
            $this->signPrivateKey = [$this->normalizeFilePath($privateKey), $privateKeyPassphrase];
        } else {
            $this->signPrivateKey = $this->normalizeFilePath($privateKey);
        }

        $this->signOptions = $signOptions;
        $this->extraCerts = $extraCerts ? realpath($extraCerts) : null;
        $this->privateKeyPassphrase = $privateKeyPassphrase;
    }

    public function sign(Message $message): Message
    {
        $bufferFile = tmpfile();
        $outputFile = tmpfile();

        $this->iteratorToFile($message->getBody()->toIterable(), $bufferFile);

        if (!@openssl_pkcs7_sign(stream_get_meta_data($bufferFile)['uri'], stream_get_meta_data($outputFile)['uri'], $this->signCertificate, $this->signPrivateKey, [], $this->signOptions, $this->extraCerts)) {
            throw new RuntimeException(sprintf('Failed to sign S/Mime message. Error: "%s".', openssl_error_string()));
        }

        return new Message($message->getHeaders(), $this->convertMessageToSMimePart($outputFile, 'multipart', 'signed'));
    }
}
