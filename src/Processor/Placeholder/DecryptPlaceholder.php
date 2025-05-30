<?php
declare(strict_types=1);
namespace Helhum\TYPO3\ConfigHandling\Processor\Placeholder;

use Defuse\Crypto\Crypto;
use Defuse\Crypto\Exception\CryptoException;
use Defuse\Crypto\Key;
use Helhum\ConfigLoader\Processor\Placeholder\PlaceholderInterface;

class DecryptPlaceholder implements PlaceholderInterface
{
    public function __construct(
        private readonly string $secret
    ) {}

    public function supportedTypes(): array
    {
        return ['decrypt'];
    }

    public function supports(string $type): bool
    {
        return $type === 'decrypt';
    }

    public function canReplace(string $accessor, array $referenceConfig = []): bool
    {
        try {
            return Key::loadFromAsciiSafeString($this->secret) ? true : false;
        } catch (CryptoException) {
            return false;
        }
    }

    public function representsValue(string $accessor, array $referenceConfig = []): ?string
    {
        try {
            return Crypto::decrypt($accessor, Key::loadFromAsciiSafeString($this->secret));
        } catch (CryptoException) {
            return null;
        }
    }
}
