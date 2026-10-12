<?php

declare(strict_types=1);

namespace Plasgos\CrmSdk\Legacy;

use Plasgos\CrmSdk\Legacy\Http\HttpClient;
use Plasgos\CrmSdk\Legacy\Resource\Official\Accounts;
use Plasgos\CrmSdk\Legacy\Resource\Official\Broadcasts;
use Plasgos\CrmSdk\Legacy\Resource\Official\Messages;
use Plasgos\CrmSdk\Legacy\Resource\Official\SharedWaba;
use Plasgos\CrmSdk\Legacy\Resource\Official\Templates;

/** `$client->official` — WhatsApp Business API (official) endpoints. */
final class Official
{
    public readonly Accounts $accounts;
    public readonly Templates $templates;
    public readonly Messages $messages;
    public readonly Broadcasts $broadcasts;
    public readonly SharedWaba $sharedWaba;

    public function __construct(HttpClient $http)
    {
        $this->accounts = new Accounts($http);
        $this->templates = new Templates($http);
        $this->messages = new Messages($http);
        $this->broadcasts = new Broadcasts($http);
        $this->sharedWaba = new SharedWaba($http);
    }
}
