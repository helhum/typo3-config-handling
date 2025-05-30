<?php
declare(strict_types=1);
namespace Helhum\TYPO3\ConfigHandling\Tests\Unit\Reader;

/***************************************************************
 *  Copyright notice
 *
 *  (c) 2018 Helmut Hummel <info@helhum.io>
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

use Helhum\TYPO3\ConfigHandling\ConfigReader\ArrayReader;
use PHPUnit\Framework\TestCase;

class ArrayReaderTest extends TestCase
{
    /**
     * @test
     */
    public function readConfigReturnsGivenArray(): void
    {
        $input = [
            'foo' => 'bar',
        ];

        $reader = new ArrayReader($input);

        $this->assertSame($input, $reader->readConfig());
    }

    /**
     * @test
     */
    public function readConfigThrowsExceptionOnInvalidPath(): void
    {
        $this->expectException(\RuntimeException::class);
        $input = [
            'foo' => 'bar',
        ];

        $reader = new ArrayReader($input, 'foo');
        $reader->readConfig();
    }

    /**
     * @test
     */
    public function readConfigReturnsGivenArrayPath(): void
    {
        $input = [
            'foo' => [
                'bar' => 'baz',
            ],
        ];

        $reader = new ArrayReader($input, 'foo');

        $this->assertSame(['bar' => 'baz'], $reader->readConfig());
    }
}
