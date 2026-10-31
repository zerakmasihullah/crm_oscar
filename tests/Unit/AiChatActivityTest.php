<?php

namespace Tests\Unit;

use App\Support\AiChatActivity;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class AiChatActivityTest extends TestCase
{
    #[Test]
    public function it_rewrites_absolute_markdown_links_to_app_paths(): void
    {
        $text = 'Created. [Open "Note"](http://example.com/field-notes/12/edit)';
        $sanitized = AiChatActivity::sanitizeText($text);

        $this->assertSame(
            'Created. [Open "Note"](/field-notes/12/edit)',
            $sanitized,
        );
    }

    #[Test]
    public function it_summarizes_delete_steps_without_llm(): void
    {
        $summary = AiChatActivity::summarize('DeleteRecord', [
            'resource' => 'field_reports',
            'id' => 9,
        ]);

        $this->assertSame('Deleting field_reports #9', $summary);
    }

    #[Test]
    public function it_extracts_successful_write_mutations(): void
    {
        $response = new \Laravel\Ai\Responses\AgentResponse(
            'inv-1',
            'Done',
            new \Laravel\Ai\Responses\Data\Usage(10, 5),
            new \Laravel\Ai\Responses\Data\Meta('openai', 'gpt-4o-mini'),
        );
        $response->toolResults = collect([
            new \Laravel\Ai\Responses\Data\ToolResult(
                'call_1',
                'CreateRecord',
                ['resource' => 'field_reports'],
                'Created Field Report. #1',
            ),
            new \Laravel\Ai\Responses\Data\ToolResult(
                'call_2',
                'DeleteRecord',
                ['resource' => 'field_reports', 'id' => 3],
                'Error: Not found',
            ),
            new \Laravel\Ai\Responses\Data\ToolResult(
                'call_3',
                'ListRecords',
                ['resource' => 'field_reports'],
                'Found 1',
            ),
        ]);

        $mutations = AiChatActivity::mutationsFromResponse($response);

        $this->assertCount(1, $mutations);
        $this->assertSame('CreateRecord', $mutations[0]['tool']);
        $this->assertSame('field_reports', $mutations[0]['resource']);
    }
}
