<?php

namespace Workbench\App\Tests\Feature;

use Illuminate\Support\Facades\DB;
use Workbench\App\Tests\TestCase;

/**
 * The reason this package does not use an in-memory spreadsheet object model:
 * a large export must never depend on the whole result set fitting in RAM.
 */
class StreamingTest extends TestCase
{
    public function test_the_result_set_is_fetched_in_chunks_not_all_at_once(): void
    {
        $this->seedPlayers(20);

        config()->set('luminix.sheets.export.chunk_size', 5);

        $selects = 0;

        DB::listen(function ($query) use (&$selects) {
            if (str_contains($query->sql, 'from "players"')) {
                $selects++;
            }
        });

        $this->actingAs($this->user())
            ->get('/luminix-api/players/export')
            ->assertStatus(200)
            ->streamedContent();

        // 20 rows at 5 per fetch: four full pages plus the empty page that ends
        // the iteration. One single query would mean the whole table in memory.
        $this->assertSame(5, $selects);
    }

    public function test_memory_use_does_not_grow_with_the_size_of_the_export(): void
    {
        $this->seedPlayers(5000);

        config()->set('luminix.sheets.export.chunk_size', 200);

        gc_collect_cycles();
        $before = memory_get_usage(true);

        $response = $this->actingAs($this->user())
            ->get('/luminix-api/players/export')
            ->assertStatus(200);

        $contents = $response->streamedContent();

        $growth = memory_get_usage(true) - $before;

        $this->assertCount(5001, $this->readSheet($contents));

        // A generous ceiling: the point is that it is bounded by the chunk, not
        // by the row count. Loading 5.000 models at once costs far more.
        $this->assertLessThan(
            16 * 1024 * 1024,
            $growth,
            'The export allocated '.round($growth / 1024 / 1024, 1).' MB for 5.000 rows.'
        );
    }

    private function seedPlayers(int $count): void
    {
        $now = now()->toDateTimeString();

        foreach (array_chunk(range(1, $count), 500) as $chunk) {
            DB::table('players')->insert(array_map(fn ($i) => [
                'name' => 'Player '.$i,
                'registration' => str_pad((string) $i, 6, '0', STR_PAD_LEFT),
                'score' => $i,
                'active' => true,
                'banned' => false,
                'created_at' => $now,
                'updated_at' => $now,
            ], $chunk));
        }
    }
}
