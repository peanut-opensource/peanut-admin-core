<?php

declare(strict_types=1);

namespace PeanutAdmin\App\setting;

use DateTimeImmutable;
use DateTimeZone;
use JsonException;
use PeanutAdmin\Settings\Application\SettingException;
use PeanutAdmin\Settings\Definition\SettingDefinition;
use PeanutAdmin\Settings\Definition\SettingDefinitionRegistry;
use think\db\BaseQuery;
use think\facade\Db;

/** Definition synchronizer for the install/upgrade lifecycle. */
final readonly class PreBootstrapSettingDefinitionSynchronizer
{
    /** @return array{inserted:int,updated:int,retired:int} */
    public function synchronize(SettingDefinitionRegistry $registry, DateTimeImmutable $now): array
    {
        return Db::transaction(function () use ($registry, $now): array {
            $moduleKeys = $registry->moduleKeys();
            if ($moduleKeys === []) {
                return ['inserted' => 0, 'updated' => 0, 'retired' => 0];
            }
            $existing = [];
            foreach ($this->definitions()->whereIn('module_key', $moduleKeys)->lock(true)->select()->toArray() as $row) {
                $existing[(string) $row['module_key'] . ':' . (string) $row['setting_key']] = $row;
            }
            $declared = [];
            $counts = ['inserted' => 0, 'updated' => 0, 'retired' => 0];
            foreach ($registry->all() as $definition) {
                $key = $definition->qualifiedKey();
                $declared[$key] = true;
                $row = $existing[$key] ?? null;
                if ($row === null) {
                    $this->definitions()->insert($this->values($definition, $now, true));
                    ++$counts['inserted'];
                    continue;
                }
                if (hash_equals((string) $row['definition_digest'], $definition->digest)
                    && $row['status'] === 'active') {
                    continue;
                }
                $this->definitions()->where('id', (int) $row['id'])->update([
                    ...$this->values($definition, $now, false),
                    'status' => 'active',
                    'revision' => (int) $row['revision'] + 1,
                ]);
                ++$counts['updated'];
            }
            foreach ($existing as $key => $row) {
                if (isset($declared[$key]) || $row['status'] === 'retired') {
                    continue;
                }
                $counts['retired'] += $this->definitions()->where('id', (int) $row['id'])->where('status', 'active')->update([
                    'status' => 'retired',
                    'revision' => (int) $row['revision'] + 1,
                    'updated_at' => self::date($now),
                ]);
            }

            return $counts;
        });
    }

    private function definitions(): BaseQuery
    {
        return Db::name('setting_definition');
    }

    /** @return array<string,mixed> */
    private function values(SettingDefinition $definition, DateTimeImmutable $now, bool $identity): array
    {
        $values = [
            'name' => $definition->name,
            'description' => $definition->description,
            'schema_json' => $this->json($definition->schema),
            'required_flag' => (int) $definition->required,
            'secret_flag' => (int) $definition->secret,
            'deployment_scope_flag' => (int) $definition->allows('deployment'),
            'tenant_scope_flag' => (int) $definition->allows('tenant'),
            'target_scope_flag' => (int) $definition->allows('target'),
            'target_resource_key' => $definition->targetResourceKey,
            'target_operation' => $definition->targetOperation,
            'default_json' => $definition->hasDefault ? $this->json($definition->defaultValue) : null,
            'definition_digest' => $definition->digest,
            'updated_at' => self::date($now),
        ];

        return $identity ? [
            'module_key' => $definition->moduleKey,
            'setting_key' => $definition->key,
            ...$values,
            'status' => 'active',
            'revision' => 1,
            'created_at' => self::date($now),
        ] : $values;
    }

    private function json(mixed $value): string
    {
        try {
            return json_encode($value, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
        } catch (JsonException) {
            throw SettingException::invalid('SETTING_VALUE_INVALID', 'The setting value cannot be encoded.');
        }
    }

    private static function date(DateTimeImmutable $date): string
    {
        return $date->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s.v');
    }
}
