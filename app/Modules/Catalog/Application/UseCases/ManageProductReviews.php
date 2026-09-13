<?php

namespace App\Modules\Catalog\Application\UseCases;

use App\Modules\Auth\Application\UseCases\AuthorizeUser;
use App\Modules\Catalog\Domain\Contracts\ProductReviewRepositoryInterface;

final class ManageProductReviews
{
    public function __construct(
        private readonly ProductReviewRepositoryInterface $reviews,
        private readonly AuthorizeUser $authorize,
    ) {}

    public function approved(int $productId): iterable { return $this->reviews->approvedForProduct($productId); }
    public function summary(int $productId): array { return $this->reviews->summary($productId); }
    public function submit(int $userId, int $productId, array $data): object { return $this->reviews->createForVerifiedCustomer($userId, $productId, $data); }

    public function all(object $actor): iterable
    {
        $this->authorize->execute($actor, 'reviews.view');

        return $this->reviews->all();
    }

    public function moderate(object $actor, int $reviewId, string $status): object
    {
        $this->authorize->execute($actor, 'reviews.manage');

        return $this->reviews->moderate($reviewId, $status);
    }
}
