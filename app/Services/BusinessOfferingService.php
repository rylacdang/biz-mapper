<?php

namespace App\Services;

use App\Models\Business;
use App\Models\BusinessOffering;
use App\Repositories\Contracts\BusinessOfferingRepositoryInterface;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Throwable;

class BusinessOfferingService
{
    public function __construct(
        private readonly BusinessOfferingRepositoryInterface $offeringRepo
    ) {}

    /**
     * Store an item and remove a new upload if persistence fails.
     */
    public function create(Business $business, array $data): BusinessOffering
    {
        $photo = $data['photo'] ?? null;
        $data = $this->prepare($data);
        $data['business_id'] = $business->id;
        $path = $photo instanceof UploadedFile ? $this->storePhoto($photo) : null;
        $data['photo'] = $path;

        try {
            return $this->offeringRepo->create($data);
        } catch (Throwable $exception) {
            if ($path) {
                Storage::disk('public')->delete($path);
            }

            throw $exception;
        }
    }

    /**
     * Keep the old photo until the replacement has been saved successfully.
     */
    public function update(BusinessOffering $offering, array $data): void
    {
        $photo = $data['photo'] ?? null;
        $remove = (bool) ($data['remove_photo'] ?? false);
        $data = $this->prepare($data);
        $path = $photo instanceof UploadedFile ? $this->storePhoto($photo) : null;

        if ($path || $remove) {
            $data['photo'] = $path;
        }

        try {
            if (! $this->offeringRepo->update($offering->id, $data)) {
                throw new RuntimeException('The catalog item could not be updated.');
            }
        } catch (Throwable $exception) {
            if ($path) {
                Storage::disk('public')->delete($path);
            }

            throw $exception;
        }

        if (($path || $remove) && $offering->photo) {
            Storage::disk('public')->delete($offering->photo);
        }
    }

    /**
     * Remove the photo only after the item has been deleted.
     */
    public function delete(BusinessOffering $offering): void
    {
        if (! $this->offeringRepo->delete($offering->id)) {
            throw new RuntimeException('The catalog item could not be removed.');
        }

        if ($offering->photo) {
            Storage::disk('public')->delete($offering->photo);
        }
    }

    /**
     * Stop saving the item if its photo cannot be stored.
     */
    private function storePhoto(UploadedFile $photo): string
    {
        $path = $photo->store('business-offerings', 'public');

        if (! $path) {
            throw new RuntimeException('The item photo could not be stored.');
        }

        return $path;
    }

    /**
     * Use Philippine pesos for priced items and clear unused price details.
     */
    private function prepare(array $data): array
    {
        unset($data['photo'], $data['remove_photo']);

        $data['price'] = $data['price'] ?? null;
        $data['currency'] = $data['price'] === null ? null : 'PHP';
        $data['price_max'] = $data['price_max'] ?? null;
        $data['price_unit'] = $data['price_unit'] ?? null;

        if ($data['price'] === null) {
            $data['price_max'] = null;
            $data['price_unit'] = null;
        }

        return $data;
    }
}
