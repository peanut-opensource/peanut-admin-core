<?php

declare(strict_types=1);

namespace PeanutAdmin\App\referencecode;

use DateTimeImmutable;
use DateTimeZone;
use PeanutAdmin\ReferenceCodes\Definition\ReferenceCodeSetDefinition;
use PeanutAdmin\ReferenceCodes\Definition\ReferenceCodeSetRegistry;
use think\db\BaseQuery;
use think\db\Raw;
use think\facade\Db;

/** Definition synchronizer for the install/upgrade lifecycle. */
final readonly class PreBootstrapReferenceCodeDefinitionSynchronizer
{
    /** @return array{inserted:int,updated:int,retired:int,reactivated:int} */
    public function synchronize(ReferenceCodeSetRegistry $registry, DateTimeImmutable $now): array
    {
        $now = $this->millisecond($now);

        return Db::transaction(function () use ($registry, $now): array {
            $rows = $this->query('pa_reference_code_set')->lock(true)->select()->toArray();
            $existing = [];
            foreach ($rows as $row) {
                $existing[(string) $row['module_key'] . ':' . (string) $row['set_key']] = $row;
            }
            $declared = [];
            $counts = ['inserted' => 0, 'updated' => 0, 'retired' => 0, 'reactivated' => 0];
            foreach ($registry->all() as $definition) {
                $qualifiedKey = $definition->qualifiedKey();
                $declared[$qualifiedKey] = true;
                $row = $existing[$qualifiedKey] ?? null;
                if ($row === null) {
                    $this->insert($definition, $now);
                    ++$counts['inserted'];
                    continue;
                }
                $reactivating = (string) $row['lifecycle'] === 'retired';
                if (!$reactivating && hash_equals((string) $row['definition_digest'], $definition->digest)) {
                    continue;
                }
                $this->query('pa_reference_code_set')->where('id', (int) $row['id'])->update([
                    'name' => $definition->name,
                    'description' => $definition->description,
                    'definition_digest' => $definition->digest,
                    'lifecycle' => 'active',
                    'revision' => $this->raw('revision + 1'),
                    'updated_at' => $this->date($now),
                ]);
                ++$counts[$reactivating ? 'reactivated' : 'updated'];
            }
            foreach ($existing as $qualifiedKey => $row) {
                if (isset($declared[$qualifiedKey]) || (string) $row['lifecycle'] === 'retired') {
                    continue;
                }
                $counts['retired'] += $this->query('pa_reference_code_set')->where('id', (int) $row['id'])
                    ->where('lifecycle', 'active')->update([
                        'lifecycle' => 'retired',
                        'revision' => $this->raw('revision + 1'),
                        'updated_at' => $this->date($now),
                    ]);
            }

            return $counts;
        });
    }

    private function insert(ReferenceCodeSetDefinition $definition, DateTimeImmutable $now): void
    {
        $this->query('pa_reference_code_set')->insert([
            'module_key' => $definition->moduleKey,
            'set_key' => $definition->key,
            'name' => $definition->name,
            'description' => $definition->description,
            'definition_digest' => $definition->digest,
            'lifecycle' => 'active',
            'revision' => 1,
            'created_at' => $this->date($now),
            'updated_at' => $this->date($now),
        ]);
    }

    private function query(string $table): BaseQuery
    {
        return Db::table($table);
    }

    private function raw(string $expression): Raw
    {
        return Db::raw($expression);
    }

    private function millisecond(DateTimeImmutable $date): DateTimeImmutable
    {
        return DateTimeImmutable::createFromFormat(
            '!Y-m-d H:i:s.v',
            $date->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s.v'),
            new DateTimeZone('UTC'),
        ) ?: throw new \RuntimeException('REFERENCE_CODE_TIMESTAMP_INVALID');
    }

    private function date(DateTimeImmutable $date): string
    {
        return $date->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s.v');
    }
}
