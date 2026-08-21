<?php

declare(strict_types=1);

namespace PeanutAdmin\App\Modules\Example\Target\Contracts;

use PeanutAdmin\Kernel\Module\ModuleException;

final readonly class TargetIdSet
{
    private const MAX_UNSIGNED_BIGINT = '18446744073709551615';

    /** @param list<string> $ids */
    private function __construct(public array $ids) {}

    /** @param list<string> $ids */
    public static function fromStrings(array $ids): self
    {
        $normalized = [];
        foreach (array_values(array_unique($ids, SORT_STRING)) as $id) {
            if (preg_match('/^[1-9][0-9]{0,19}$/D', $id) !== 1
                || (strlen($id) === 20 && strcmp($id, self::MAX_UNSIGNED_BIGINT) > 0)) {
                throw new ModuleException('AUTHZ_TARGET_NOT_FOUND', 'Target identifier is invalid.');
            }
            $normalized[] = $id;
        }

        return new self($normalized);
    }

    public function json(): string
    {
        return json_encode($this->ids, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
    }
}
