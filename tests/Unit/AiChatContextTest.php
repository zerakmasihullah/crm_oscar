<?php

namespace Tests\Unit;

use App\Support\AiChatContext;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class AiChatContextTest extends TestCase
{
    #[Test]
    public function it_normalizes_page_context(): void
    {
        $context = AiChatContext::normalize([
            'label' => 'Field note #9',
            'path' => '/field-notes/9/edit',
            'resource' => 'field_notes',
            'record_id' => 9,
        ]);

        $this->assertNotNull($context);
        $this->assertSame('field_notes', $context['resource']);
        $this->assertSame(9, $context['record_id']);
        $this->assertStringContainsString('Field note #9', AiChatContext::promptBlock($context));
    }

    #[Test]
    public function it_rejects_empty_context(): void
    {
        $this->assertNull(AiChatContext::normalize([
            'label' => '',
            'path' => '/field-notes',
        ]));
    }
}
