<?php

namespace App\Events;

use Illuminate\Queue\SerializesModels;

class TenantSwitched
{
    use SerializesModels;

    public $user;

    public $tenantId;

    public function __construct($user, string $tenantId)
    {
        $this->user = $user;
        $this->tenantId = $tenantId;
    }
}
