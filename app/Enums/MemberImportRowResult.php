<?php

namespace App\Enums;

enum MemberImportRowResult: string
{
    /** A new account was created and added to the club. */
    case Created = 'created';

    /** An existing account was added to the club. */
    case Linked = 'linked';

    /** The person already is an active member; nothing changed. */
    case Skipped = 'skipped';
}
