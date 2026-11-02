<?php

use App\Actions\MemberImports\ImportMemberRow;
use App\Enums\MemberImportStatus;
use App\Enums\StaffRole;
use App\Jobs\ProcessMemberImport;
use App\Livewire\Members\Import;
use App\Mail\ImportedMemberMail;
use App\Models\Club;
use App\Models\MemberImport;
use App\Models\Membership;
use App\Models\User;
use App\Services\MemberImports\MemberCsv;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

beforeEach(function () {
    Storage::fake();

    $this->club = Club::factory()->create();
    $this->owner = User::factory()->create();
    $this->club->addStaff($this->owner, StaffRole::Owner);
});

/**
 * Store a CSV as an uploaded member list and return the import for it.
 *
 * @param  array<string, int>  $columnMap
 */
function memberImportFor(Club $club, string $csv, array $columnMap, bool $sendInvitations = false): MemberImport
{
    Storage::put('member-imports/list.csv', $csv);

    return MemberImport::factory()->for($club)->create([
        'path' => 'member-imports/list.csv',
        'column_map' => $columnMap,
        'send_invitations' => $sendInvitations,
    ]);
}

function runMemberImport(MemberImport $import): MemberImport
{
    (new ProcessMemberImport($import))->handle(app(ImportMemberRow::class));

    return $import->refresh();
}

test('the csv reader handles semicolons, a byte order mark and Windows-1252 text', function () {
    $content = "\xEF\xBB\xBFVoornaam;Achternaam;E-mail;Lid sinds\n"
        .mb_convert_encoding('José;Müller;jose@example.com;01-09-2024', 'Windows-1252', 'UTF-8')."\n\n";
    $stream = fopen('php://memory', 'w+b');
    fwrite($stream, $content);
    rewind($stream);

    $csv = MemberCsv::open($stream);

    expect($csv->headers())->toBe(['Voornaam', 'Achternaam', 'E-mail', 'Lid sinds'])
        ->and(iterator_to_array($csv->rows()))->toBe([2 => ['José', 'Müller', 'jose@example.com', '01-09-2024']])
        ->and($csv->guessColumnMap())->toBe(['first_name' => 0, 'last_name' => 1, 'email' => 2, 'joined_at' => 3]);
});

test('an import creates accounts, links existing ones and skips current members', function () {
    $existing = User::factory()->create(['email' => 'bestaand@example.com']);
    $alreadyMember = User::factory()->create(['email' => 'lid@example.com']);
    $this->club->addMember($alreadyMember);

    $import = runMemberImport(memberImportFor($this->club, implode("\n", [
        'Naam,E-mail,Lidnummer,Lid sinds,Telefoon,Geboortedatum',
        'Nieuw Lid,NIEUW@example.com,501,15-01-2020,0612345678,1990-03-14',
        'Bestaand Lid,bestaand@example.com,,,,',
        'Al Lid,lid@example.com,,,,',
    ]), ['name' => 0, 'email' => 1, 'membership_number' => 2, 'joined_at' => 3, 'phone' => 4, 'date_of_birth' => 5]));

    $membership = Membership::query()->where('club_id', $this->club->id)
        ->whereRelation('user', 'email', 'nieuw@example.com')->sole();

    expect($import->status)->toBe(MemberImportStatus::Completed)
        ->and([$import->total_rows, $import->created_count, $import->linked_count, $import->skipped_count, $import->failed_count])->toBe([3, 1, 1, 1, 0])
        ->and($membership->membership_number)->toBe(501)
        ->and($membership->joined_at->toDateString())->toBe('2020-01-15')
        ->and($membership->phone)->toBe('0612345678')
        ->and($membership->date_of_birth->toDateString())->toBe('1990-03-14')
        ->and($existing->isMemberOf($this->club))->toBeTrue()
        ->and($this->club->refresh()->next_membership_number)->toBeGreaterThan(501)
        ->and(Storage::exists('member-imports/list.csv'))->toBeFalse();
});

test('invalid rows are reported and do not stop the import', function () {
    Membership::query()->where('club_id', $this->club->id)->delete();
    $taken = $this->club->addMember(User::factory()->create());

    $import = runMemberImport(memberImportFor($this->club, implode("\n", [
        'Voornaam,Achternaam,E-mail,Lidnummer,Geboortedatum',
        'Geen,Mail,niet-een-adres,,',
        ',,naamloos@example.com,,',
        'Fout,Datum,datum@example.com,,31-02-1990',
        'Dubbel,Nummer,dubbel@example.com,'.$taken->membership_number.',',
        'Goed,Lid,goed@example.com,,',
    ]), ['first_name' => 0, 'last_name' => 1, 'email' => 2, 'membership_number' => 3, 'date_of_birth' => 4]));

    expect($import->created_count)->toBe(1)
        ->and($import->failed_count)->toBe(4)
        ->and(collect($import->row_errors)->pluck('row')->all())->toBe([2, 3, 4, 5])
        ->and(User::query()->where('email', 'goed@example.com')->exists())->toBeTrue()
        ->and(User::query()->where('email', 'naamloos@example.com')->exists())->toBeFalse();
});

test('invitations are queued only when asked for, telling new and existing accounts apart', function () {
    Mail::fake();
    User::factory()->create(['email' => 'bestaand@example.com']);
    $csv = "Naam,E-mail\nNieuw Lid,nieuw@example.com\nBestaand Lid,bestaand@example.com";

    runMemberImport(memberImportFor($this->club, $csv, ['name' => 0, 'email' => 1]));
    Mail::assertNothingQueued();

    $otherClub = Club::factory()->create();
    runMemberImport(memberImportFor($otherClub, "Naam,E-mail\nNog Nieuwer,nieuwer@example.com\nBestaand Lid,bestaand@example.com", ['name' => 0, 'email' => 1], sendInvitations: true));

    Mail::assertQueuedCount(2);
    Mail::assertQueued(ImportedMemberMail::class, fn (ImportedMemberMail $mail) => $mail->hasTo('nieuwer@example.com') && $mail->accountCreated);
    Mail::assertQueued(ImportedMemberMail::class, fn (ImportedMemberMail $mail) => $mail->hasTo('bestaand@example.com') && ! $mail->accountCreated);
});

test('the import stops when the club cannot add more members', function () {
    $this->club->update(['trial_ends_at' => now()->subDay()]);

    $import = runMemberImport(memberImportFor($this->club, "Naam,E-mail\nEen,een@example.com\nTwee,twee@example.com", ['name' => 0, 'email' => 1]));

    expect($import->created_count)->toBe(0)
        ->and($import->skipped_count)->toBe(2)
        ->and($import->row_errors)->toHaveCount(1)
        ->and(User::query()->whereIn('email', ['een@example.com', 'twee@example.com'])->exists())->toBeFalse();
});

test('an owner uploads a list, checks the guessed columns and starts the import', function () {
    Queue::fake();
    $file = UploadedFile::fake()->createWithContent('leden.csv', "Voornaam;Achternaam;E-mail\nAnna;de Vries;anna@example.com\n");

    $component = Livewire::actingAs($this->owner)
        ->test(Import::class, ['club' => $this->club])
        ->set('file', $file)
        ->assertSet('headers', ['Voornaam', 'Achternaam', 'E-mail'])
        ->assertSet('columnMap.email', '2')
        ->assertSee('Anna')
        ->set('send_invitations', true)
        ->call('start')
        ->assertHasNoErrors();

    $import = MemberImport::query()->sole();

    expect($import->column_map)->toBe(['first_name' => 0, 'last_name' => 1, 'email' => 2])
        ->and($import->send_invitations)->toBeTrue()
        ->and(Storage::exists($import->path))->toBeTrue();

    $component->assertSet('importId', $import->id);
    Queue::assertPushed(ProcessMemberImport::class);
});

test('an import needs an email and a name column', function () {
    Queue::fake();
    $file = UploadedFile::fake()->createWithContent('leden.csv', "Iets,Anders\na,b\n");

    Livewire::actingAs($this->owner)
        ->test(Import::class, ['club' => $this->club])
        ->set('file', $file)
        ->call('start')
        ->assertHasErrors(['columnMap.email'])
        ->set('columnMap.email', '0')
        ->call('start')
        ->assertHasErrors(['columnMap.name']);

    Queue::assertNothingPushed();
});

test('only the owner can import, and only reports of their own club are shown', function () {
    $admin = User::factory()->create();
    $this->club->addStaff($admin, StaffRole::Admin);

    Livewire::actingAs($admin)
        ->test(Import::class, ['club' => $this->club])
        ->assertForbidden();

    $foreignImport = MemberImport::factory()->completed()->create(['original_name' => 'andere-club.csv']);

    Livewire::actingAs($this->owner)
        ->withQueryParams(['import' => $foreignImport->id])
        ->test(Import::class, ['club' => $this->club])
        ->assertDontSee('andere-club.csv');
});

test('the example file lists every supported column', function () {
    Livewire::actingAs($this->owner)
        ->test(Import::class, ['club' => $this->club])
        ->call('downloadTemplate')
        ->assertFileDownloaded();
});

test('the invitation points new accounts to choosing a password and existing ones to logging in', function () {
    $membership = $this->club->addMember(User::factory()->create(['email' => 'anna@example.com']));

    expect((new ImportedMemberMail($membership, accountCreated: true))->render())
        ->toContain(route('password.request'))
        ->toContain('anna@example.com')
        ->and((new ImportedMemberMail($membership, accountCreated: false))->render())
        ->toContain(route('dashboard'))
        ->not->toContain(route('password.request'));
});
