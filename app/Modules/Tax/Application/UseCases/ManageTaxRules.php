<?php

namespace App\Modules\Tax\Application\UseCases;

use App\Modules\Auth\Application\UseCases\AuthorizeUser;
use App\Modules\Tax\Domain\Contracts\TaxRuleRepositoryInterface;

final class ManageTaxRules
{
    public function __construct(
        private readonly TaxRuleRepositoryInterface $rules,
        private readonly AuthorizeUser $authorize,
    ) {}

    public function list(object $actor): iterable
    {
        $this->authorize->execute($actor, 'taxes.view');

        return $this->rules->list();
    }

    public function show(object $actor, int $id): object
    {
        $this->authorize->execute($actor, 'taxes.view');

        return $this->rules->find($id);
    }

    public function store(object $actor, array $data): object
    {
        $this->authorize->execute($actor, 'taxes.create');

        return $this->rules->create($data);
    }

    public function update(object $actor, int $id, array $data): object
    {
        $this->authorize->execute($actor, 'taxes.update');

        return $this->rules->update($id, $data);
    }

    public function remove(object $actor, int $id): void
    {
        $this->authorize->execute($actor, 'taxes.delete');
        $this->rules->delete($id);
    }
}
