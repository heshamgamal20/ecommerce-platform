<?php

namespace App\Modules\Staff\Application\UseCases;

use App\Modules\Auth\Application\UseCases\AuthorizeUser;
use App\Modules\Staff\Domain\Contracts\AuditLogRepositoryInterface;
use App\Modules\Staff\Domain\Contracts\StaffRepositoryInterface;
use App\Modules\Staff\Domain\Exceptions\StaffActionNotAllowedException;

final class DeleteStaff
{
    public function __construct(
        private readonly StaffRepositoryInterface $staff,
        private readonly AuditLogRepositoryInterface $audit,
        private readonly AuthorizeUser $authorize,
    ) {}

    public function execute(object $target, object $actor): void
    {
        $this->authorize->execute($actor, 'assistants.delete');
        if ($target->is($actor)) {
            throw new StaffActionNotAllowedException('A staff user cannot delete their own account.');
        }
        if ($target->hasRole('owner')) {
            throw new StaffActionNotAllowedException('The owner account cannot be deleted.');
        }
        $this->staff->delete($target);
        $this->audit->record($actor, 'staff.deleted', get_class($actor), $target->id);
    }
}
