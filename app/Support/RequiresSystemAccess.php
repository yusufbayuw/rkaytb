<?php

namespace App\Support;

trait RequiresSystemAccess
{
    public function bootRequiresSystemAccess(): void
    {
        SystemAccess::authorize();
    }
}
