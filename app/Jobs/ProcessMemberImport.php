<?php

namespace App\Jobs;

use App\Actions\MemberImports\ImportMemberRow;
use App\Enums\MemberImportRowResult;
use App\Enums\MemberImportStatus;
use App\Exceptions\ClubPlanException;
use App\Exceptions\InvalidMemberImportRow;
use App\Models\Club;
use App\Models\MemberImport;
use App\Services\MemberImports\MemberCsv;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Storage;
use Throwable;

class ProcessMemberImport implements ShouldQueue
{
    use Queueable;

    /** Not retried: a rerun would send invitations twice. Rows already imported are skipped on a manual retry. */
    public int $tries = 1;

    public int $timeout = 3600;

    /** Progress is saved every this many rows, so the page can show it. */
    protected const int PROGRESS_EVERY = 25;

    public function __construct(public MemberImport $import) {}

    public function handle(ImportMemberRow $importMemberRow): void
    {
        $import = $this->import;
        $club = $import->club;

        $import->update(['status' => MemberImportStatus::Processing]);

        $csv = MemberCsv::open(Storage::readStream($import->path));
        $columns = $import->column_map;

        $this->prepare($import, $club, $csv, $columns);

        $errors = [];

        foreach ($csv->rows() as $rowNumber => $cells) {
            $values = array_map(fn (int $index): string => trim($cells[$index] ?? ''), $columns);

            try {
                $result = $importMemberRow->handle($club, $values, $import->send_invitations);

                match ($result) {
                    MemberImportRowResult::Created => $import->created_count++,
                    MemberImportRowResult::Linked => $import->linked_count++,
                    MemberImportRowResult::Skipped => $import->skipped_count++,
                };
            } catch (InvalidMemberImportRow $exception) {
                $import->failed_count++;
                $errors[] = ['row' => $rowNumber, 'message' => $exception->getMessage()];
            } catch (ClubPlanException $exception) {
                // The member limit is reached: every following row would hit it too.
                $import->skipped_count += $import->total_rows - $import->processed_rows;
                $import->processed_rows = $import->total_rows;
                $errors[] = ['row' => $rowNumber, 'message' => $exception->getMessage()];

                break;
            }

            $import->processed_rows++;

            if ($import->processed_rows % self::PROGRESS_EVERY === 0) {
                $import->row_errors = array_slice($errors, 0, MemberImport::MAX_ROW_ERRORS);
                $import->save();
            }
        }

        $import->forceFill([
            'status' => MemberImportStatus::Completed,
            'row_errors' => array_slice($errors, 0, MemberImport::MAX_ROW_ERRORS),
            'finished_at' => now(),
        ])->save();

        Storage::delete($import->path);
    }

    public function failed(Throwable $exception): void
    {
        $this->import->forceFill([
            'status' => MemberImportStatus::Failed,
            'row_errors' => [['row' => 0, 'message' => $exception->getMessage()]],
            'finished_at' => now(),
        ])->save();

        Storage::delete($this->import->path);
    }

    /**
     * Count the rows for the progress bar and move the club's membership
     * number counter past the highest number in the list, so numbers handed
     * out during the import never collide with numbers further down the file.
     *
     * @param  array<string, int>  $columns
     */
    protected function prepare(MemberImport $import, Club $club, MemberCsv $csv, array $columns): void
    {
        $total = 0;
        $highestNumber = 0;

        foreach ($csv->rows() as $cells) {
            $total++;

            $number = trim($cells[$columns['membership_number'] ?? -1] ?? '');

            if (ctype_digit($number)) {
                $highestNumber = max($highestNumber, (int) $number);
            }
        }

        $import->update(['total_rows' => $total]);

        if ($highestNumber > 0) {
            Club::query()
                ->whereKey($club->id)
                ->where('next_membership_number', '<=', $highestNumber)
                ->update(['next_membership_number' => $highestNumber + 1]);
        }
    }
}
