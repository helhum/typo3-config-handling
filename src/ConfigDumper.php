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
use Helhum\ConfigLoader\Processor\PlaceholderValue;
use Symfony\Component\Yaml\Yaml;
use TYPO3\CMS\Core\Utility\ArrayUtility;
use TYPO3\CMS\Core\Utility\GeneralUtility;

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
                    $fileContent .= Yaml::dump(['imports' => $config['imports']], 2) . chr(10);
                    unset($config['imports']);
                }
                $fileContent .= Yaml::dump($config, 5);
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
        return GeneralUtility::writeFile(
            $file,
            $fileContent,
            true
        );
    }

    private function generateCommentBlock(string $comment, string $commentChar = '//'): string
    {
        if (empty($comment)) {
            return '';
        }
        return implode(
            chr(10),
            array_map(function ($line) use ($commentChar) {
                return $commentChar . ' ' . $line;
            }, explode(chr(10), $comment))
        );
    }

    /**
     * Inspired by \TYPO3\CMS\Core\Utility\ArrayUtility::arrayExport
     * but cleaned up and more powerful
     *
     * @param array $config
     * @param array $referenceConfig
     * @param array $path
     * @return string
     */
    public function getPhpCode(array $config, array $referenceConfig = [], array $path = []): string
    {
        $lines = '[' . LF;
        $level = count($path);
        foreach ($config as $key => $value) {
            // Indention
            $lines .= str_repeat('    ', $level + 1);
            // Integer / string keys
            $lines .= is_int($key) ? $key . ' => ' : $this->getPhpCodeForValue($key, $referenceConfig) . ' => ';
            if (is_array($value)) {
                if (!empty($value)) {
                    if ($level === 2 && $path[0] === 'EXT' && $path[1] === 'extConf') {
                        $lines .= 'serialize(';
                        $lines .= substr($this->getPhpCode($this->addDotsToTypoScript($value), $referenceConfig, array_merge($path, [$key])), 0, -2);
                        $lines .= '),' . LF;
                    } else {
                        $lines .= $this->getPhpCode($value, $referenceConfig, array_merge($path, [$key]));
                    }
                } else {
                    $lines .= '[],' . LF;
                }
            } elseif (is_int($value) || is_float($value)) {
                $lines .= $value . ',' . LF;
            } elseif ($value === null) {
                $lines .= 'null' . ',' . LF;
            } elseif (is_bool($value)) {
                $lines .= $value ? 'true' : 'false';
                $lines .= ',' . LF;
            } elseif (is_string($value)) {
                $lines .= $this->getPhpCodeForValue($value, $referenceConfig, $path) . ',' . LF;
            } else {
                throw new \RuntimeException('Objects are not supported', 1519779656);
            }
        }
        $lines .= str_repeat('    ', $level) . ']' . ($level === 0 ? '' : ',' . LF);
        return $lines;
    }

    private function isPlaceHolder($value): bool
    {
        return is_string($value) && preg_match(PlaceholderValue::PLACEHOLDER_PATTERN, $value);
    }

    private function getPhpCodeForValue(string $value, array $referenceConfig, array $path = []): string
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
                $phpCode = substr($this->getPhpCode(Config::getValue($referenceConfig, $placeholderInfo['accessor']), $referenceConfig, $path), 0, -2);
                break;
            case 'global':
                $globalPath = str_getcsv($placeholderInfo['accessor'], '.');
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

    private function extractPlaceHolder($value, array $types = null): array
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
