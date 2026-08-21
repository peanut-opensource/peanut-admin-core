<?php

declare(strict_types=1);

namespace PeanutAdmin\App\controller\api\v1;

use PeanutAdmin\App\Modules\Example\Target\Contracts\TargetIdSet;
use PeanutAdmin\DataPermission\Exception\DataAuthorizationException;
use PeanutAdmin\DataPermission\Target\TypedResourceTargetCollection;
use PeanutAdmin\DataPermission\Target\TypedResourceTargetSet;
use PeanutAdmin\Kernel\Authorization\Application\AdminAccessException;
use PeanutAdmin\Kernel\Module\ModuleException;
use think\Request;

final class ExampleHttpRuntime
{
    private function __construct() {}

    public static function queryTargets(Request $request, int $maximum = 5_000): TypedResourceTargetCollection
    {
        $resourceKey = $request->get('target_resource_key');
        $targetRole = $request->get('target_role');
        $targetIds = $request->get('target_id');
        if (!is_string($resourceKey) || $resourceKey === ''
            || !is_string($targetRole) || $targetRole === '') {
            throw AdminAccessException::invalid(
                'AUTHZ_TARGET_INPUT_INVALID',
                'Target resource key and target role are required.',
            );
        }
        $ids = is_array($targetIds)
            ? $targetIds
            : (is_string($targetIds) ? explode(',', $targetIds) : [$targetIds]);
        if ($ids === [] || count($ids) > $maximum) {
            throw AdminAccessException::invalid(
                'AUTHZ_TARGET_CARDINALITY_INVALID',
                "Target selection requires 1 to {$maximum} identifiers.",
            );
        }

        return new TypedResourceTargetCollection([
            new TypedResourceTargetSet($resourceKey, self::ids($ids), $targetRole),
        ]);
    }

    /** @param array<string, mixed> $body */
    public static function bodyTargets(array $body): TypedResourceTargetCollection
    {
        $sets = [self::bodyTarget($body, 'target', 'primary')];
        if (array_key_exists('related_target', $body) && $body['related_target'] !== null) {
            $sets[] = self::bodyTarget($body, 'related_target', 'related');
        }

        return new TypedResourceTargetCollection($sets);
    }

    /** @param array<string, mixed> $body */
    public static function policyTargets(array $body): TypedResourceTargetCollection
    {
        $input = $body['targets'] ?? null;
        if (!is_array($input) || !array_is_list($input) || $input === []) {
            throw AdminAccessException::invalid('AUTHZ_TARGET_INPUT_INVALID', 'Policy targets are required.');
        }
        $sets = [];
        foreach ($input as $index => $target) {
            if (!is_array($target)) {
                throw AdminAccessException::invalid('AUTHZ_TARGET_INPUT_INVALID', "Policy target {$index} is invalid.");
            }
            self::onlyKeys($target, ['target_resource_key', 'target_role', 'target_ids']);
            $resourceKey = $target['target_resource_key'] ?? null;
            $role = $target['target_role'] ?? null;
            $ids = $target['target_ids'] ?? null;
            if (!is_string($resourceKey) || $resourceKey === ''
                || !is_string($role) || $role === ''
                || !is_array($ids) || !array_is_list($ids)
                || $ids === [] || count($ids) > 500) {
                throw AdminAccessException::invalid('AUTHZ_TARGET_INPUT_INVALID', "Policy target {$index} is invalid.");
            }
            $sets[] = new TypedResourceTargetSet($resourceKey, self::ids($ids), $role);
        }

        return new TypedResourceTargetCollection($sets);
    }

    /**
     * @param array<string, mixed> $body
     * @param list<string> $allowed
     */
    public static function onlyKeys(array $body, array $allowed): void
    {
        $unknown = array_diff(array_keys($body), $allowed);
        if ($unknown !== []) {
            throw AdminAccessException::invalid(
                'REQUEST_BODY_INVALID',
                'Unknown request fields: ' . implode(', ', $unknown) . '.',
            );
        }
    }

    public static function bigint(string $value, string $field): string
    {
        try {
            return TargetIdSet::fromStrings([$value])->ids[0];
        } catch (ModuleException) {
            throw AdminAccessException::invalid(strtoupper($field) . '_INVALID', "{$field} is invalid.");
        }
    }

    /** @param array<string, mixed> $body */
    public static function requiredString(array $body, string $field): string
    {
        $value = $body[$field] ?? null;
        if (!is_string($value) || trim($value) === '') {
            throw AdminAccessException::invalid(strtoupper($field) . '_INVALID', "{$field} is required.");
        }

        return $value;
    }

    /** @param array<string, mixed> $body */
    public static function optionalString(array $body, string $field): ?string
    {
        if (!array_key_exists($field, $body)) {
            return null;
        }
        if (!is_string($body[$field])) {
            throw AdminAccessException::invalid(strtoupper($field) . '_INVALID', "{$field} must be a string.");
        }

        return $body[$field];
    }

    public static function translate(ModuleException|DataAuthorizationException $exception): never
    {
        if ($exception->errorCode === 'REVISION_MISMATCH') {
            throw AdminAccessException::revisionMismatch();
        }
        if (in_array($exception->errorCode, [
            'AUTHZ_TARGET_NOT_FOUND',
            'AUTHZ_DATA_DENIED',
            'AUTHZ_SHARED_MASTER_SCOPE_DENIED',
            'AUTHZ_SHARED_SCOPE_DENIED',
        ], true)) {
            throw new AdminAccessException(
                'AUTHZ_DATA_DENIED',
                404,
                'The requested resource does not exist or is not accessible.',
            );
        }
        if (in_array($exception->errorCode, [
            'AUTHZ_TARGET_CARDINALITY_INVALID',
            'AUTHZ_TARGET_TYPE_MISMATCH',
            'REFERENCE_SEARCH_INVALID',
            'WORK_ITEM_POLICY_CONFIG_INVALID',
            'WORK_ITEM_POLICY_NAME_INVALID',
            'WORK_ITEM_SORT_INVALID',
            'WORK_ITEM_STATUS_INVALID',
            'WORK_ITEM_TITLE_INVALID',
            'WORK_ITEM_UPDATE_EMPTY',
        ], true)) {
            throw AdminAccessException::invalid($exception->errorCode, $exception->getMessage());
        }

        throw $exception;
    }

    /** @param array<string, mixed> $body */
    private static function bodyTarget(array $body, string $field, string $expectedRole): TypedResourceTargetSet
    {
        $target = $body[$field] ?? null;
        if (!is_array($target)) {
            throw AdminAccessException::invalid('AUTHZ_TARGET_INPUT_INVALID', "{$field} is required.");
        }
        self::onlyKeys($target, ['target_resource_key', 'target_role', 'target_id']);
        $resourceKey = $target['target_resource_key'] ?? null;
        $role = $target['target_role'] ?? null;
        $id = $target['target_id'] ?? null;
        if (!is_string($resourceKey) || $resourceKey === ''
            || $role !== $expectedRole || !is_string($id)) {
            throw AdminAccessException::invalid('AUTHZ_TARGET_INPUT_INVALID', "{$field} is invalid.");
        }

        return new TypedResourceTargetSet($resourceKey, self::ids([$id]), $role);
    }

    /**
     * @param array<mixed> $values
     * @return list<string>
     */
    private static function ids(array $values): array
    {
        if (!array_is_list($values)) {
            throw AdminAccessException::invalid('AUTHZ_TARGET_INPUT_INVALID', 'Target identifiers must be a list.');
        }
        foreach ($values as $value) {
            if (!is_string($value)) {
                throw AdminAccessException::invalid('AUTHZ_TARGET_INPUT_INVALID', 'Target identifiers must be strings.');
            }
        }
        try {
            return TargetIdSet::fromStrings($values)->ids;
        } catch (ModuleException) {
            throw AdminAccessException::invalid('AUTHZ_TARGET_INPUT_INVALID', 'A target identifier is invalid.');
        }
    }
}
