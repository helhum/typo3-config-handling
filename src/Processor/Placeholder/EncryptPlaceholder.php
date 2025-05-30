<?php
declare(strict_types=1);
namespace Helhum\TYPO3\ConfigHandling\Processor\Placeholder;

use Defuse\Crypto\Crypto;
use Defuse\Crypto\Key;
use Helhum\ConfigLoader\Processor\Placeholder\PlaceholderInterface;

class EncryptPlaceholder implements PlaceholderInterface
{
    public function __construct(
        private readonly string $secret
    ) {}

    public function supportedTypes(): array
    {
        return ['encrypt'];
    }

    public function supports(string $type): bool
    {
        return $type === 'encrypt';
    }

    public function canReplace(string $accessor, array $referenceConfig = []): bool
    {
        return true;
    }

    public function representsValue(string $accessor, array $referenceConfig = []): string
    {
        $key = Key::loadFromAsciiSafeString($this->secret);

        return '%decrypt(' . Crypto::encrypt($accessor, $key) . ')%';
    }
}
