<?php
declare(strict_types=1);
namespace Helhum\TYPO3\ConfigHandling;

/***************************************************************
 *  Copyright notice
 *
 *  (c) 2017 Helmut Hummel <info@helhum.io>
 *  All rights reserved
 *
 *  The GNU General Public License can be found at
 *  http://www.gnu.org/copyleft/gpl.html.
 *  A copy is found in the text file GPL.txt and important notices to the license
 *  from the author is found in LICENSE.txt distributed with these scripts.
 *
 *
 *  This script is distributed in the hope that it will be useful,
 *  but WITHOUT ANY WARRANTY; without even the implied warranty of
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *  GNU General Public License for more details.
 *
 *  This copyright notice MUST APPEAR in all copies of the script!
 ***************************************************************/

use Helhum\ConfigLoader\Config;
use Helhum\ConfigLoader\InvalidConfigurationFileException;
use Helhum\ConfigLoader\Processor\PlaceholderValue;
use Symfony\Component\Yaml\Yaml;
use TYPO3\CMS\Core\Utility\ArrayUtility;

class ConfigDumper
{
    public function dumpToFile(array $config, string $file, string $comment = ''): bool
    {
        $type = pathinfo($file, PATHINFO_EXTENSION);
        $fileContent = '';
        switch ($type) {
            case 'yml':
            case 'yaml':
                $fileContent .= $this->generateCommentBlock($comment, '#');
                if (!empty($config['imports'])) {
                    $fileContent .= Yaml::dump(['imports' => $config['imports']], 2);
                    unset($config['imports']);
                    if (empty($config['processors'])) {
                        $fileContent .= chr(10);
                    }
                }
                if (!empty($config['processors'])) {
                    $fileContent .= Yaml::dump(['processors' => $config['processors']], 2) . chr(10);
                    unset($config['processors']);
                }
                if ($fileContent === '' || !empty($config)) {
                    $fileContent .= Yaml::dump($config, 6);
                }
                break;
            case 'php':
            default:
                $exportedConfig = ArrayUtility::arrayExport($config);
                $fileContent = <<<EOF
<?php
{$this->generateCommentBlock($comment)}
return $exportedConfig;

EOF;
        }

        return (bool)file_put_contents($file, $fileContent);
    }

    private function generateCommentBlock(string $comment, string $commentChar = '//'): string
    {
        if (empty($comment)) {
            return '';
        }

        return implode(
            chr(10),
            array_map(
                fn(string $line): string => $commentChar . ' ' . $line,
                explode(chr(10), $comment))
        ) . chr(10);
    }

    /**
     * Returns a PHP representation of a value, including value with placeholder
     *
     * @param mixed $value
     * @param array $referenceConfig
     * @param array $path
     *
     * @return string
     */
    public function getPhpCode($value, array $referenceConfig = [], array $path = []): string
    {
        if (is_array($value)) {
            $level = count($path);
            if ($value === []) {
                $code = '[]';
            } else {
                $code = '[' . PHP_EOL;
                foreach ($value as $key => $arrayValue) {
                    // Indention
                    $code .= str_repeat('    ', $level + 1);
                    // Integer / string keys
                    $code .= is_int($key) ? $key . ' => ' : $this->getPhpCodeForPlaceholder($key, $referenceConfig, [], true) . ' => ';
                    if ($level === 2 && $path[0] === 'EXT' && $path[1] === 'extConf' && \is_array($arrayValue)) {
                        $code .= 'serialize(';
                        $code .= $this->getPhpCode($this->addDotsToTypoScript($arrayValue), $referenceConfig, array_merge($path, [$key]));
                        $code .= ')';
                    } else {
                        $code .= $this->getPhpCode($arrayValue, $referenceConfig, array_merge($path, [$key]));
                    }
                    $code .= ',' . PHP_EOL;
                }
                $code .= str_repeat('    ', $level) . ']';
            }
        } elseif (is_int($value) || is_float($value)) {
            $code = (string)$value;
        } elseif ($value === null) {
            $code = 'null';
        } elseif (is_bool($value)) {
            $code = $value ? 'true' : 'false';
        } elseif (is_string($value)) {
            $code = $this->getPhpCodeForPlaceholder($value, $referenceConfig, $path);
        } else {
            throw new \RuntimeException('Objects, closures and resources are not supported', 1519779656);
        }

        return $code;
    }

    private function isPlaceHolder(string $value): bool
    {
        return preg_match(PlaceholderValue::PLACEHOLDER_PATTERN, $value);
    }

    private function getPhpCodeForPlaceholder(string $value, array $referenceConfig, array $path = [], bool $forKey = false): string
    {
        if (!$this->isPlaceHolder($value)) {
            return '\'' . $this->escapePhpValue($value) . '\'';
        }
        $placeholderInfo = $this->extractPlaceHolder($value);
        $phpCode = '\'' . $this->escapePhpValue($value) . '\'';
        switch ($placeholderInfo['type']) {
            case 'env':
                $phpCode = 'getenv(\'' . $this->escapePhpValue($placeholderInfo['accessor']) . '\')';
                break;
            case 'const':
                $phpCode = 'constant(\'' . $this->escapePhpValue($placeholderInfo['accessor']) . '\')';
                break;
            case 'conf':
                $subConfig = Config::getValue($referenceConfig, $placeholderInfo['accessor']);
                if ($forKey && !is_scalar($subConfig)) {
                    throw new InvalidConfigurationFileException(sprintf('Cannot use conf placeholder "%s" as array key, when it references an array', $value), 1519826280);
                }
                $phpCode = $this->getPhpCode($subConfig, $referenceConfig, $path);
                break;
            case 'global':
                $globalPath = str_getcsv($placeholderInfo['accessor'], '.', escape: '\\');
                $phpCode = '$GLOBALS[\'' . implode('\'][\'', array_map([$this, 'escapePhpValue'], $globalPath)) . '\']';
                break;
        }
        if ($placeholderInfo['isDirectMatch']) {
            return $phpCode;
        }

        return '\'' . preg_replace(PlaceholderValue::PLACEHOLDER_PATTERN, '\' . ' . $phpCode . ' . \'', $value) . '\'';
    }

    private function escapePhpValue(string $value): string
    {
        return addcslashes($value, '\\\'');
    }

    private function extractPlaceHolder(string $value, ?array $types = null): array
    {
        if (!$this->isPlaceHolder($value)) {
            return [];
        }
        preg_match(PlaceholderValue::PLACEHOLDER_PATTERN, $value, $matches);
        if ($types !== null && !in_array($matches[1], $types, true)) {
            return [];
        }

        return [
            'placeholder' => $matches[0],
            'type' => $matches[1],
            'accessor' => $matches[2],
            'isDirectMatch' => $matches[0] === $value,
        ];
    }

    /**
     * @param array $typoScript TypoScript configuration array
     *
     * @return array TypoScript configuration array without dots at the end of all keys
     */
    private function addDotsToTypoScript(array $typoScript): array
    {
        $out = [];
        foreach ($typoScript as $key => $value) {
            if (is_array($value)) {
                $key .= '.';
                $out[$key] = $this->addDotsToTypoScript($value);
            } else {
                $out[$key] = $value;
            }
        }

        return $out;
    }
}
