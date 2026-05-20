<?php

declare(strict_types=1);

namespace Token27\NexusAI\Prompts\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Token27\NexusAI\Prompts\Engine\MustacheAdapter;

final class MustacheAdapterTest extends TestCase
{
    private MustacheAdapter $adapter;

    protected function setUp(): void
    {
        $this->adapter = new MustacheAdapter();
    }

    public function testRenderSimpleVariable(): void
    {
        $result = $this->adapter->render('Hello, {{name}}!', ['name' => 'World']);

        static::assertSame('Hello, World!', $result);
    }

    public function testRenderMultipleVariables(): void
    {
        $result = $this->adapter->render('{{a}} + {{b}} = {{c}}', ['a' => '1', 'b' => '2', 'c' => '3']);

        static::assertSame('1 + 2 = 3', $result);
    }

    public function testRenderSectionWhenValueIsTrue(): void
    {
        $result = $this->adapter->render('{{#show}}visible{{/show}}', ['show' => true]);

        static::assertSame('visible', $result);
    }

    public function testRenderSectionWhenValueIsFalse(): void
    {
        $result = $this->adapter->render('{{#show}}visible{{/show}}', ['show' => false]);

        static::assertSame('', $result);
    }

    public function testRenderInvertedSectionWhenEmpty(): void
    {
        $result = $this->adapter->render('{{^items}}No items{{/items}}', ['items' => []]);

        static::assertSame('No items', $result);
    }

    public function testRenderMissingVariableRendersEmpty(): void
    {
        $result = $this->adapter->render('Hello, {{name}}!', []);

        static::assertSame('Hello, !', $result);
    }

    public function testSupportsHelpers(): void
    {
        static::assertTrue($this->adapter->supportsHelpers());
    }

    public function testRegisterHelperIsCallableInTemplate(): void
    {
        $this->adapter->registerHelper('uppercase', static fn(string $text) => strtoupper($text));

        // Mustache helpers are available as variables in context
        $result = $this->adapter->render('{{#uppercase}}hello{{/uppercase}}', []);

        static::assertSame('HELLO', $result);
    }
}
