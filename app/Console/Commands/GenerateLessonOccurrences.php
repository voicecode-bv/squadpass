<?php

namespace App\Console\Commands;

use App\Models\Lesson;
use App\Services\Lessons\OccurrenceGenerator;
use Illuminate\Console\Command;

class GenerateLessonOccurrences extends Command
{
    protected $signature = 'lessons:generate-occurrences';

    protected $description = 'Generate upcoming lesson occurrences for all active lessons';

    public function handle(OccurrenceGenerator $generator): int
    {
        $generated = 0;

        Lesson::query()
            ->active()
            ->with('club')
            ->chunkById(100, function ($lessons) use ($generator, &$generated) {
                foreach ($lessons as $lesson) {
                    $generated += $generator->generateForLesson($lesson);
                }
            });

        $this->info("Generated {$generated} lesson occurrences.");

        return self::SUCCESS;
    }
}
