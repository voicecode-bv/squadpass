<?php

namespace App\Livewire\Members;

use App\Jobs\ProcessMemberImport;
use App\Models\Club;
use App\Models\MemberImport;
use App\Services\MemberImports\MemberCsv;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use InvalidArgumentException;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;
use Symfony\Component\HttpFoundation\StreamedResponse;

#[Title('Import members')]
class Import extends Component
{
    use WithFileUploads;

    /** Rows shown in the preview while matching columns. */
    protected const int PREVIEW_ROWS = 5;

    public Club $club;

    public ?TemporaryUploadedFile $file = null;

    /** @var list<string> */
    #[Locked]
    public array $headers = [];

    /** @var list<list<string>> */
    #[Locked]
    public array $preview = [];

    /** @var array<string, string> import field => CSV column index, or '' when not imported */
    public array $columnMap = [];

    public bool $send_invitations = false;

    /** The import whose progress and report are shown. */
    #[Url(as: 'import')]
    public ?int $importId = null;

    public function mount(Club $club): void
    {
        $this->authorize('update', $club);

        $this->club = $club;
    }

    /**
     * Read the header and first rows as soon as a file is picked, and guess
     * which column holds which field.
     */
    public function updatedFile(): void
    {
        $this->authorize('update', $this->club);

        $this->validateOnly('file', $this->fileRules());

        try {
            $csv = MemberCsv::open($this->file->readStream());
        } catch (InvalidArgumentException $exception) {
            $this->addError('file', $exception->getMessage());
            $this->resetUpload(keepErrors: true);

            return;
        }

        $this->headers = $csv->headers();
        $this->preview = [];

        foreach ($csv->rows() as $row) {
            $this->preview[] = array_map(fn (string $value): string => mb_strimwidth($value, 0, 40, '…'), $row);

            if (count($this->preview) === self::PREVIEW_ROWS) {
                break;
            }
        }

        $guessed = $csv->guessColumnMap();

        $this->columnMap = collect(MemberCsv::FIELDS)
            ->mapWithKeys(fn (string $field): array => [$field => isset($guessed[$field]) ? (string) $guessed[$field] : ''])
            ->all();
    }

    public function resetUpload(bool $keepErrors = false): void
    {
        $this->reset('file', 'headers', 'preview', 'columnMap', 'send_invitations');

        if (! $keepErrors) {
            $this->resetErrorBag();
        }
    }

    /**
     * Store the file and process it in the background.
     */
    public function start(): void
    {
        $this->authorize('update', $this->club);

        $columnIndexes = implode(',', array_keys($this->headers));

        $this->validate([
            ...$this->fileRules(),
            'columnMap.email' => ['required', 'in:'.$columnIndexes],
            'columnMap.*' => ['nullable', 'in:'.$columnIndexes],
        ], [
            'columnMap.email.required' => __('Choose the column with the email address.'),
        ]);

        $columns = array_map('intval', array_filter($this->columnMap, fn (?string $index): bool => $index !== null && $index !== ''));

        if (! isset($columns['name']) && ! isset($columns['first_name']) && ! isset($columns['last_name'])) {
            $this->addError('columnMap.name', __('Choose the column with the name, or the columns with the first and last name.'));

            return;
        }

        $import = MemberImport::query()->create([
            'club_id' => $this->club->id,
            'user_id' => Auth::id(),
            'path' => $this->file->store('member-imports'),
            'original_name' => mb_substr($this->file->getClientOriginalName(), 0, 255),
            'column_map' => $columns,
            'send_invitations' => $this->send_invitations,
        ]);

        ProcessMemberImport::dispatch($import);

        $this->resetUpload();
        $this->importId = $import->id;
    }

    public function startOver(): void
    {
        $this->importId = null;
        unset($this->memberImport);
    }

    /**
     * An example file with every supported column, semicolon separated so it
     * opens in columns in a Dutch Excel.
     */
    public function downloadTemplate(): StreamedResponse
    {
        $this->authorize('update', $this->club);

        $fields = ['first_name', 'last_name', 'email', 'membership_number', 'joined_at', 'phone', 'date_of_birth'];
        $example = ['Anna', 'de Vries', 'anna@example.com', '101', '01-09-2024', '0612345678', '14-03-1990'];

        return response()->streamDownload(function () use ($fields, $example): void {
            $output = fopen('php://output', 'wb');
            fwrite($output, "\xEF\xBB\xBF");
            fputcsv($output, array_map(fn (string $field): string => MemberCsv::label($field), $fields), ';', '"', '');
            fputcsv($output, $example, ';', '"', '');
            fclose($output);
        }, __('member-list-template').'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /**
     * The import shown on the page, scoped to this club.
     */
    #[Computed]
    public function memberImport(): ?MemberImport
    {
        if ($this->importId === null) {
            return null;
        }

        return MemberImport::query()->where('club_id', $this->club->id)->find($this->importId);
    }

    /**
     * @return array<string, list<string>>
     */
    protected function fileRules(): array
    {
        return ['file' => ['required', 'file', 'mimes:csv,txt', 'max:10240']];
    }

    public function render(): View
    {
        return view('livewire.members.import');
    }
}
