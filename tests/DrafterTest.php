<?php
/**
 * Test drafter binary wrapper.
 *
 * todo, test:
 *  - output cannot be written to disk
 *  - sourcemap cannot be written to disk
 *  - validate options is set, validation fails
 *  - use line num behavior
 *
 * @author    hmaus
 * @since     2015-08-28
 * @copyright 2015 (c) Hendrik Maus
 * @license   All rights reserved.
 * @package   DrafterPhp
 */

namespace DrafterPhp\Tests;

use DrafterPhp\Drafter;
use DrafterPhp\DrafterInterface;

class DrafterTest extends \PHPUnit_Framework_TestCase
{
    /**
     * Path to the drafter bin for the tests.
     * @var string
     */
    private $binPath;

    /**
     * Base path for apib fixtures for the tests.
     * @var string
     */
    private $fixturePath;

    /**
     * Drafter php instance.
     * @var DrafterInterface
     */
    private $drafter;

    /**
     * Init test case.
     */
    protected function setUp()
    {
        $this->binPath     = __DIR__ . '/../vendor/bin/drafter';
        $this->fixturePath = __DIR__ . '/fixtures/';
        $this->drafter     = new Drafter($this->binPath);
    }

    /**
     * Create a drafter instance; check if it worked.
     */
    public function testCreateWrapperObject()
    {
        $this->assertInstanceOf('\DrafterPhp\Drafter', $this->drafter);
    }

    /**
     * Test configuring the path to drafter binary.
     */
    public function testSettingAndGettingTheBinaryPath()
    {
        $this->assertEquals($this->binPath, $this->drafter->getBinary());
        $this->drafter->setBinary('new.path');
        $this->assertEquals('new.path', $this->drafter->getBinary());
    }

    /**
     * Get drafter binary version.
     */
    public function testGetVersion()
    {
        $version = $this->drafter->version()->run();

        // the drafter binary will add a line break at the end of the version string
        $version = trim($version);

        // Assert the fixed version of drafter that this package currently supports
        $this->assertEquals('v0.1.9', $version);
        $this->assertRegExp('/v\d+\.\d+\.\d+/', $version);
    }

    /**
     * Parse a simple example to json ast.
     */
    public function testParseSimplestExampleToJson()
    {
        $result = $this
            ->drafter
            ->input($this->fixturePath . 'simplest-example.apib')
            ->format('json')
            ->run();

        $this->assertNotNull($result);
        $this->assertJsonStringEqualsJsonFile(
            $this->fixturePath . 'simplest-example-ast.json',
            $result
        );

        return $this->drafter;
    }

    /**
     * The drafter instance will hold its state; we can invoke `run` again for the same result.
     *
     * @param Drafter $drafter
     * @depends testParseSimplestExampleToJson
     */
    public function testRunningDrafterAgainWithoutReset(Drafter $drafter)
    {
        $this->assertJsonStringEqualsJsonFile(
            $this->fixturePath . 'simplest-example-ast.json',
            $drafter->run()
        );
    }

    /**
     * Build the process instance using drafter and pass it to the run method.
     */
    public function testBuildProcessAndPassItToRunMethod()
    {
        $process = $this
            ->drafter
            ->input($this->fixturePath . 'simplest-example.apib')
            ->format('json')
            ->build();

        $this->assertInstanceOf('\Symfony\Component\Process\Process', $process);

        $result = $this
            ->drafter
            ->run($process);

        $this->assertJsonStringEqualsJsonFile(
            $this->fixturePath . 'simplest-example-ast.json',
            $result
        );
    }

    /**
     * Test turing the drafter json result into both php array and object.
     */
    public function testTurnDrafterResultIntoPhpDataStructure()
    {
        $result = $this
            ->drafter
            ->input($this->fixturePath . 'simplest-example.apib')
            ->format('json')
            ->run();

        $astFixture = file_get_contents($this->fixturePath . 'simplest-example-ast.json');

        $phpObject      = json_decode($result);
        $expectedPhpObj = json_decode($astFixture);
        $this->assertEquals($expectedPhpObj, $phpObject);

        $phpArray = json_decode($result, true);
        $expectedPhpArray = json_decode($astFixture, true);
        $this->assertEquals($expectedPhpArray, $phpArray);
    }

    /**
     * Test setting all supported options with correct syntax.
     */
    public function testSetAllAvailableOptions()
    {
        $this
            ->drafter
            ->input('some-input')
            ->output('some-output')
            ->version()
            ->validate()
            ->format('json')
            ->sourcemap('some-sourcemap')
            ->useLineNum();

        $expected = [
            '--output'       => 'some-output',
            '--version'      => '',
            '--validate'     => '',
            '--format'       => 'json',
            '--sourcemap'    => 'some-sourcemap',
            '--use-line-num' => ''
        ];

        $options = $this->drafter->getOptions();

        $this->assertEquals($expected, $options);
    }

    /**
     * Test resetting the input argument.
     */
    public function testResetInputArgument()
    {
        $this
            ->drafter
            ->input('some-input');

        $this->assertEquals('some-input', $this->drafter->getInput());

        $this
            ->drafter
            ->resetInput();

        $this->assertNull($this->drafter->getInput());
    }

    /**
     * Test resetting all options on the instance.
     */
    public function testResetOptions()
    {
        $this
            ->drafter
            ->version();

        $this->assertCount(1, $this->drafter->getOptions());

        $this
            ->drafter
            ->resetOptions();

        $this->assertCount(0, $this->drafter->getOptions());
    }

    /**
     * Catch an exception if the binary cannot be found.
     *
     * @expectedException \Exception
     * @expectedExceptionMessage sh: INVALID: command not found
     */
    public function testInvalidBinaryWillThrowException()
    {
        $this
            ->drafter
            ->setBinary('INVALID')
            ->version()
            ->run();
    }

    /**
     * Catch an exception if the input file cannot be opened.
     *
     * @expectedException \Exception
     * @expectedExceptionMessage fatal: unable to open file 'INVALID'
     */
    public function testInvalidInputArgumentWillThrowException()
    {
        $this
            ->drafter
            ->input('INVALID')
            ->run();
    }

    /**
     * Catch an exception if an invalid format is passed.
     *
     * @expectedException \Exception
     * @expectedExceptionMessage option value is invalid: --format=INVALID
     */
    public function testInvalidFormatWillThrowException()
    {
        $this
            ->drafter
            ->input($this->fixturePath . 'simplest-example.apib')
            ->format('INVALID')
            ->run();
    }

    /**
     * Catch an exception if no option or argument is set.
     *
     * @expectedException \Exception
     * @expectedExceptionMessage Input argument missing
     */
    public function testExceptionWhenInputArgumentIsMissing()
    {
        $this->drafter->run();
    }
}
