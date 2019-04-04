<?php
declare(strict_types=1);
namespace Helhum\TYPO3\ConfigHandling\Processor\Placeholder;

use Defuse\Crypto\Crypto;
use Defuse\Crypto\Key;
use Helhum\ConfigLoader\Processor\Placeholder\PlaceholderInterface;

class EncryptPlaceholder implements PlaceholderInterface
{
    /**
     * @var string
     */
    private $secret;

    public function __construct(string $secret)
    {
        $this->secret = $secret;
    }

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

    public function representsValue(string $accessor, array $referenceConfig = [])
    {
        $key = Key::loadFromAsciiSafeString($this->secret);

        return '%decrypt(' . Crypto::encrypt($accessor, $key) . ')%';
    }
}
