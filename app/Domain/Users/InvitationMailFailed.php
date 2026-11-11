<?php

namespace App\Domain\Users;

use RuntimeException;

/**
 * The invitation row was rolled back because the email could not be handed to the mail server.
 */
class InvitationMailFailed extends RuntimeException
{
    public const MESSAGE = 'The invitation could not be emailed. Check the MAIL_* settings on the server and try again.';
}
