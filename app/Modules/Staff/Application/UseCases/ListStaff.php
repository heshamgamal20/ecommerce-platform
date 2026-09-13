<?php

namespace App\Modules\Staff\Application\UseCases;

use App\Modules\Auth\Application\UseCases\AuthorizeUser;
use App\Modules\Staff\Domain\Contracts\StaffRepositoryInterface;

final class ListStaff
{
    public function __construct(
        private readonly StaffRepositoryInterface $staff,
        private readonly AuthorizeUser $authorize,
    ) {}

    public function execute(object $actor): mixed
    {
        $this->authorize->execute($actor, 'assistants.view');

        return $this->staff->list();
    }
}
