<?php

namespace App\Services;

use App\Http\Resources\ProductUpdateResource;
use App\Models\ProductUpdate;
use App\Support\AccessControl;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class AdminProductUpdateService
{
    /** @return Collection<int, ProductUpdate> */
    public function listUpdates(): Collection
    {
        return ProductUpdate::query()
            ->orderByDesc('priority')
            ->orderByDesc('created_at')
            ->get();
    }

    /** @param array<string, mixed> $payload */
    public function createUpdate(array $payload): array
    {
        $validated = $this->validatePayload($payload);

        if (! empty($validated['is_active']) && empty($validated['published_at'])) {
            $validated['published_at'] = now();
        }

        $update = ProductUpdate::query()->create($validated);

        return (new ProductUpdateResource($update))->resolve();
    }

    /** @param array<string, mixed> $payload */
    public function updateUpdate(ProductUpdate $update, array $payload): array
    {
        $validated = $this->validatePayload($payload, updating: true);

        $update->update($validated);

        return (new ProductUpdateResource($update->fresh()))->resolve();
    }

    public function deleteUpdate(ProductUpdate $update): void
    {
        $update->delete();
    }

    public function toggleActive(ProductUpdate $update): array
    {
        $becomingActive = ! $update->is_active;
        $data = ['is_active' => $becomingActive];

        if ($becomingActive && $update->published_at === null) {
            $data['published_at'] = now();
        }

        $update->update($data);

        return (new ProductUpdateResource($update->fresh()))->resolve();
    }

    /** @param array<string, mixed> $payload */
    private function validatePayload(array $payload, bool $updating = false): array
    {
        $validator = Validator::make($payload, [
            'title' => [$updating ? 'sometimes' : 'required', 'string', 'min:3', 'max:200'],
            'subtitle' => ['nullable', 'string', 'max:200'],
            'body' => [$updating ? 'sometimes' : 'required', 'string', 'min:3', 'max:5000'],
            'bullets' => ['nullable', 'array', 'max:8'],
            'bullets.*' => ['string', 'max:300'],
            'location_hint' => ['nullable', 'string', 'max:300'],
            'kind' => ['nullable', 'string', 'in:new,update,tip,general'],
            'module_id' => ['nullable', 'string', 'max:64'],
            'required_tier' => ['nullable', 'string', 'in:all,free,pro,premium'],
            'audience_role' => ['nullable', 'string', 'in:all,admin,editor,reader'],
            'cta_label' => ['nullable', 'string', 'max:120'],
            'cta_href' => ['nullable', 'string', 'max:500'],
            'hero_icon' => ['nullable', 'string', 'max:64'],
            'is_active' => ['nullable', 'boolean'],
            'published_at' => ['nullable', 'date'],
            'expires_at' => ['nullable', 'date', 'after:published_at'],
            'priority' => ['nullable', 'integer', 'min:0', 'max:1000'],
        ]);

        if ($validator->fails()) {
            throw ValidationException::withMessages($validator->errors()->toArray());
        }

        $validated = $validator->validated();

        if (array_key_exists('module_id', $validated) && $validated['module_id'] !== null) {
            if (! in_array($validated['module_id'], AccessControl::MODULES, true)) {
                throw ValidationException::withMessages([
                    'module_id' => ['Érvénytelen modul azonosító.'],
                ]);
            }
        }

        if (array_key_exists('bullets', $validated) && is_array($validated['bullets'])) {
            $validated['bullets'] = array_values(array_filter(
                array_map(static fn ($item) => trim((string) $item), $validated['bullets']),
                static fn ($item) => $item !== '',
            ));
        }

        foreach (['subtitle', 'location_hint', 'cta_label', 'cta_href', 'hero_icon', 'module_id'] as $nullableString) {
            if (array_key_exists($nullableString, $validated) && $validated[$nullableString] === '') {
                $validated[$nullableString] = null;
            }
        }

        if (array_key_exists('required_tier', $validated) && $validated['required_tier'] === 'all') {
            $validated['required_tier'] = null;
        }

        if (array_key_exists('audience_role', $validated) && $validated['audience_role'] === 'all') {
            $validated['audience_role'] = null;
        }

        return $validated;
    }
}
