<?php

declare(strict_types=1);

namespace IllumaLaw\VaultCipher\Support;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class TenantModelRotator
{
    public function __construct(protected TenantKeyRotator $rotator) {}

    /**
     * @param  Builder<Model>  $query
     * @param  list<string>  $columns
     */
    public function rotateQuery(Builder $query, array $columns, int $chunkSize = 200): void
    {
        $query->chunkById($chunkSize, function (iterable $records) use ($columns): void {
            foreach ($records as $record) {
                /** @var Model $record */
                $updates = [];

                foreach ($columns as $column) {
                    $rawValue = $record->getRawOriginal($column);

                    if (! is_string($rawValue) || $rawValue === '') {
                        continue;
                    }

                    $updates[$column] = $this->rotator->rotateString($rawValue);
                }

                if ($updates !== []) {
                    $resolvedModelClass = $record::class;
                    $resolvedModelClass::query()
                        ->whereKey($record->getKey())
                        ->update($updates);
                }
            }
        });
    }
}
