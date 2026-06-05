<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Models\ProjectContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\Test;

class ProjectContextSchemaTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_has_google_drive_id_and_is_global_columns()
    {
        $this->assertTrue(Schema::hasColumn('project_contexts', 'google_drive_id'));
        $this->assertTrue(Schema::hasColumn('project_contexts', 'is_global'));
    }

    #[Test]
    public function it_casts_is_global_to_boolean()
    {
        $context = new ProjectContext(['is_global' => 1]);
        $this->assertIsBool($context->is_global);
        $this->assertTrue($context->is_global);
    }

    #[Test]
    public function drive_sync_fields_are_fillable()
    {
        $context = new ProjectContext([
            'google_drive_id' => 'drive-123',
            'is_global' => true,
        ]);

        $this->assertEquals('drive-123', $context->google_drive_id);
        $this->assertTrue($context->is_global);
    }
}
