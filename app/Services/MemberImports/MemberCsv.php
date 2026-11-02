<?php

namespace App\Services\MemberImports;

use Generator;
use InvalidArgumentException;

/**
 * Reads a member list exported from another system. Handles what spreadsheet
 * exports throw at us: comma, semicolon or tab delimiters, a UTF-8 byte order
 * mark and Windows-1252 text. Rows are streamed, so large files are fine.
 */
class MemberCsv
{
    /**
     * The fields an import can fill, in display order.
     *
     * @var list<string>
     */
    public const array FIELDS = ['name', 'first_name', 'last_name', 'email', 'membership_number', 'joined_at', 'phone', 'date_of_birth'];

    /**
     * Header texts (normalised) that map to a field automatically.
     *
     * @var array<string, list<string>>
     */
    protected const array HEADER_ALIASES = [
        'name' => ['name', 'full name', 'naam', 'volledige naam'],
        'first_name' => ['first name', 'firstname', 'given name', 'voornaam', 'roepnaam'],
        'last_name' => ['last name', 'lastname', 'surname', 'family name', 'achternaam'],
        'email' => ['email', 'e mail', 'email address', 'e mail address', 'mail', 'e mailadres', 'emailadres'],
        'membership_number' => ['membership number', 'member number', 'member id', 'lidnummer', 'lid nummer', 'relatienummer'],
        'joined_at' => ['joined', 'joined at', 'member since', 'start date', 'lid sinds', 'lid vanaf', 'inschrijfdatum', 'startdatum'],
        'phone' => ['phone', 'phone number', 'telephone', 'mobile', 'telefoon', 'telefoonnummer', 'mobiel', 'mobiel nummer'],
        'date_of_birth' => ['date of birth', 'birth date', 'birthdate', 'birthday', 'geboortedatum'],
    ];

    /** @var list<string> */
    protected array $headers;

    /**
     * @param  resource  $stream  a seekable stream positioned at the start
     */
    protected function __construct(protected $stream, protected string $delimiter)
    {
        $this->headers = array_map(fn (string $header): string => trim($header), $this->readRow() ?? []);
    }

    /**
     * Open a CSV from any stream (a local upload or a file on S3). The content
     * is copied to a temporary stream so it can be read more than once.
     *
     * @param  resource  $source
     */
    public static function open($source): self
    {
        $stream = fopen('php://temp/maxmemory:'.(4 * 1024 * 1024), 'w+b');
        stream_copy_to_stream($source, $stream);
        rewind($stream);

        // Skip a UTF-8 byte order mark so it does not stick to the first header.
        if (fread($stream, 3) !== "\xEF\xBB\xBF") {
            rewind($stream);
        }

        $start = ftell($stream);
        $firstLine = (string) fgets($stream);
        fseek($stream, $start);

        $csv = new self($stream, self::detectDelimiter($firstLine));

        if ($csv->headers === [] || $csv->headers === ['']) {
            throw new InvalidArgumentException(__('The file has no header row.'));
        }

        return $csv;
    }

    public static function label(string $field): string
    {
        return match ($field) {
            'name' => __('Full name'),
            'first_name' => __('First name'),
            'last_name' => __('Last name'),
            'email' => __('Email'),
            'membership_number' => __('Membership number'),
            'joined_at' => __('Member since'),
            'phone' => __('Phone'),
            'date_of_birth' => __('Date of birth'),
            default => throw new InvalidArgumentException("Unknown import field [{$field}]."),
        };
    }

    /**
     * @return list<string>
     */
    public function headers(): array
    {
        return $this->headers;
    }

    /**
     * Data rows keyed by their line number in the file (the header is row 1).
     * Blank lines are skipped.
     *
     * @return Generator<int, list<string>>
     */
    public function rows(): Generator
    {
        $this->rewindToFirstRow();

        $rowNumber = 1;

        while (($row = $this->readRow()) !== null) {
            $rowNumber++;

            if (implode('', $row) === '') {
                continue;
            }

            yield $rowNumber => $row;
        }
    }

    /**
     * Guess which column holds which field from the header texts.
     *
     * @return array<string, int>
     */
    public function guessColumnMap(): array
    {
        $map = [];

        foreach ($this->headers as $index => $header) {
            $normalised = self::normalise($header);

            foreach (self::HEADER_ALIASES as $field => $aliases) {
                if (! isset($map[$field]) && in_array($normalised, $aliases, true)) {
                    $map[$field] = $index;

                    break;
                }
            }
        }

        return $map;
    }

    /**
     * @return list<string>|null
     */
    protected function readRow(): ?array
    {
        $row = fgetcsv($this->stream, null, $this->delimiter, '"', '');

        if ($row === false) {
            return null;
        }

        return array_map(fn (?string $value): string => self::toUtf8((string) $value), $row);
    }

    protected function rewindToFirstRow(): void
    {
        rewind($this->stream);

        if (fread($this->stream, 3) !== "\xEF\xBB\xBF") {
            rewind($this->stream);
        }

        $this->readRow();
    }

    protected static function detectDelimiter(string $line): string
    {
        $counts = [';' => substr_count($line, ';'), ',' => substr_count($line, ','), "\t" => substr_count($line, "\t")];
        arsort($counts);

        return array_key_first($counts[array_key_first($counts)] > 0 ? $counts : [',' => 0]);
    }

    protected static function toUtf8(string $value): string
    {
        return mb_check_encoding($value, 'UTF-8') ? $value : mb_convert_encoding($value, 'UTF-8', 'Windows-1252');
    }

    protected static function normalise(string $header): string
    {
        return trim(preg_replace('/[^a-z0-9]+/', ' ', mb_strtolower($header)));
    }
}
